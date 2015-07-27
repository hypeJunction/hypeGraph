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
use hypeJunction\Inbox\Actions\SendMessage;

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

		if (elgg_is_active_plugin('hypeInbox')) {

			$action = new SendMessage();
			$action->subject = $params->subject;
			$action->body = $params->message;
			$action->attachment_guids = array();
			$action->sender_guid = $from->guid;
			$action->recipient_guids = $to->guid;

			$attachment_uids = (array) $params->attachment_uids;
			foreach ($attachment_uids as $uid) {
				$attachment = $this->graph->get($uid);
				if ($attachment && $attachment->origin == 'graph' && $attachment->access_id == ACCESS_PRIVATE) {
					$action->attachment_guids[] = $attachment->guid;
				} else {
					hypeGraph()->logger->log("Can not use node $uid as attachment. Only resources uploaded via Graph API with private access can be attached.", "ERROR");
				}
			}

			try {
				$action->validate();
				$action->execute();
				$message = $action->entity;
				if (!$message) {
					throw new Exception(implode(', ', $action->getResult()->getErrors()));
				}
			} catch (Exception $ex) {
				throw new GraphException($ex->getMessage());
			}

			return array('nodes' => array($message));
		} else {
			$subject = strip_tags($params->subject);
			$message = $params->message;
			$id = messages_send($subject, $message, $to->guid, $from->guid);
			return array('nodes' => array(get_entity($id)));
		}
	}

}
