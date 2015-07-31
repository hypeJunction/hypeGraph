<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;
use hypeJunction\Inbox\AccessCollection;
use hypeJunction\Inbox\Config;
use hypeJunction\Inbox\Message as InboxMessage;

class MessageReplies extends Controller {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_GET :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('limit', false, Parameter::TYPE_INT, elgg_get_config('default_limit')),
					new Parameter('offset', false, Parameter::TYPE_INT, 0),
					new Parameter('sort', false, Parameter::TYPE_ARRAY, array('time_created' => 'DESC')),
				);

			case HttpRequest::METHOD_POST :
				$params = array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
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

		$entity = get_entity($params->guid);

		if (elgg_is_active_plugin('hypeInbox')) {
			$getter = 'elgg_get_entities_from_metadata';
			$options = array(
				'types' => 'object',
				'subtypes' => 'messages',
				'limit' => $params->limit,
				'offset' => $params->offset,
				'sort' => $params->sort,
				'owner_guids' => $entity->owner_guid, // get only user's copy
				'metadata_name_value_pairs' => array(
					'name' => 'msgHash', 'value' => $entity->msgHash,
				),
			);
		} else {
			$getter = 'elgg_get_entities_from_relationship';
			$options = array(
				'types' => 'object',
				'subtypes' => 'messages',
				'relationship' => 'reply',
				'relationship_guid' => $entity->guid,
				'inverse_relationship' => true,
				'limit' => $params->limit,
				'offset' => $params->offset,
				'sort' => $params->sort,
				'owner_guids' => $entity->owner_guid, // get only user's copy
			);
		}
		return new BatchResult($getter, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {

		$reply_to = get_entity($params->guid);

		$from = elgg_get_logged_in_user_entity();

		$subject = strip_tags($params->subject);
		$message = $params->message;

		if (elgg_is_active_plugin('hypeInbox')) {

			if (!$reply_to instanceof InboxMessage) {
				throw new GraphException('Can not instantiate the message');
			}

			$action = new SendMessage();
			$action->entity = $reply_to;
			$action->subject = $params->subject;
			$action->body = $params->message;
			$action->attachment_guids = array();
			$action->sender_guid = $from->guid;
			$action->recipient_guids = $reply_to->getParticipantGuids();

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
				if ($action->validate() !== false) {
					$action->execute();
				}
				$message = $action->entity;
				if (!$message) {
					throw new Exception(implode(', ', $action->getResult()->getErrors()));
				}
			} catch (Exception $ex) {
				throw new GraphException($ex->getMessage());
			}

			return array('nodes' => array($message));
		} else {
			$id = messages_send($subject, $message, $reply_to->fromId, $from->guid, $reply_to->guid);
			return array('nodes' => array(get_entity($id)));
		}
	}

}
