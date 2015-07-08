<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\HttpResponse;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;
use hypeJunction\Inbox\AccessCollection;
use hypeJunction\Inbox\Config;
use hypeJunction\Inbox\Message as InboxMessage;

class UserMessages extends Controller {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_GET :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('box', true, Parameter::TYPE_ENUM, 'inbox', array('inbox', 'outbox'), elgg_echo('graph:message:box_type')),
					new Parameter('limit', false, Parameter::TYPE_INT, elgg_get_config('default_limit')),
					new Parameter('offset', false, Parameter::TYPE_INT, 0),
					new Parameter('sort', false, Parameter::TYPE_ARRAY, array('time_created' => 'DESC')),
				);

			case HttpRequest::METHOD_POST :
				$params = array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('subject', true, Parameter::TYPE_STRING, null, null, elgg_echo('messages:title')),
					new Parameter('message', true, Parameter::TYPE_STRING, null, null, elgg_echo('messages:message')),
				);
				if (elgg_is_active_plugin('hypeInbox')) {
					$params[] = new Parameter('attachment_uids', false, Parameter::TYPE_ARRAY);
				}
				return $params;

			default :
				return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		$user = get_entity($params->guid);
		if (!$user->canEdit()) {
			throw new GraphException("You are not allowed to view this user's inbox", HttpResponse::HTTP_FORBIDDEN);
		}

		$options = array(
			'types' => 'object',
			'subtypes' => 'messages',
			'limit' => $params->limit,
			'offset' => $params->offset,
			'sort' => $params->sort,
			'owner_guids' => $user->guid, // get only user's copy
			'preload_owners' => true,
			'preload_containers' => true,
		);

		if ($params->box == 'inbox') {
			$options['metadata_name_value_pairs'] = array(
				'name' => 'toId', 'value' => $user->guid,
			);
		} else if ($params->box == 'outbox') {
			$options['metadata_name_value_pairs'] = array(
				'name' => 'fromId', 'value' => $user->guid,
			);
		}

		return new BatchResult('elgg_get_entities', $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {

		$to = get_entity($params->guid);
		$from = elgg_get_logged_in_user_entity();

		if ($to->guid == $from->guid) {
			throw new GraphException("Can not send a private messageto self", 403);
		}

		$subject = strip_tags($params->subject);
		$message = $params->message;

		if (elgg_is_active_plugin('hypeInbox')) {
			$message_type = InboxMessage::TYPE_PRIVATE;

			$attachments = array();
			$attachment_uids = (array) $params->attachment_uids;
			foreach ($attachment_uids as $uid) {
				$attachment = $this->graph->get($uid);
				if ($attachment && $attachment->origin == 'graph' && $attachment->access_id == ACCESS_PRIVATE) {
					$attachments[] = $attachment;
				} else {
					hypeGraph()->logger->log("Can not use node $uid as attachment. Only resources uploaded via Graph API with private access can be attached.", "ERROR");
				}
			}

			$access_id = AccessCollection::create(array($to->guid, $from->guid))->getCollectionId();
			foreach ($attachments as $attachment) {
				$attachment->origin = 'messages';
				$attachment->access_id = $access_id;
				$attachment->save();
			}

			$message = hypeInbox()->actions->sendMessage(array(
				'sender' => $from->guid,
				'recipients' => array($to->guid),
				'subject' => $subject,
				'body' => $message,
				'attachments' => $attachments,
			));

			if (!$message) {
				throw new GraphException(elgg_echo('inbox:send:error:generic'));
			}

			$sender = $message->getSender();
			$message_type = $message->getMessageType();
			$message_hash = $message->getHash();

			$config = new Config;
			$ruleset = $config->getRuleset($message_type);

			$body = array_filter(array(
				($ruleset->hasSubject()) ? $message->subject : '',
				$message->getBody(),
				implode(', ', array_map(array(hypeInbox()->model, 'getLinkTag'), $attachments))
			));

			$notification_body = implode(PHP_EOL, $body);

			$type_label = strtolower($ruleset->getSingularLabel($to->language));
			$subject = elgg_echo('inbox:notification:subject', array($type_label), $to->language);
			$notification = elgg_echo('inbox:notification:body', array(
				$type_label,
				$sender->name,
				$notification_body,
				elgg_view('output/url', array(
					'href' => $message->getURL(),
				)),
				$sender->name,
				elgg_view('output/url', array(
					'href' => elgg_normalize_url("messages/thread/$message_hash#reply")
				)),
					), $to->language);

			$summary = elgg_echo('inbox:notification:summary', array($type_label), $to->language);

			notify_user($to->guid, $sender->guid, $subject, $notification, array(
				'action' => 'send',
				'object' => $message,
				'summary' => $summary,
			));
			return array('nodes' => array($message));
		} else {
			$id = messages_send($subject, $message, $to->guid, $from->guid);
			return array('nodes' => array(get_entity($id)));
		}
	}

}
