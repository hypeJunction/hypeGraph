<?php

namespace hypeJunction\Graph\Controllers;

use ElggUser;
use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\HttpResponse;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class UserFriends extends Controller {

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
					new Parameter('sort', false, Parameter::TYPE_ARRAY, array('alpha' => 'ASC')),
				);

			case HttpRequest::METHOD_POST :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('friend_uid', false, Parameter::TYPE_INT, null, null, elgg_echo('graph:add_friend:friend_uid')),
				);

			case HttpRequest::METHOD_DELETE :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('friend_uid', false, Parameter::TYPE_INT, null, null, elgg_echo('graph:remove_friend:friend_uid')),
				);

			default :
				return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		$options = array(
			'types' => 'user',
			'relationship' => 'friend',
			'relationship_guid' => sanitize_int($params->guid),
			'inverse_relationship' => false,
			'limit' => $params->limit,
			'offset' => $params->offset,
			'sort' => $params->sort,
		);
		return new BatchResult('elgg_get_entities_from_relationship', $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {

		$user = get_entity($params->guid);
		$friend = $params->friend_uid ? $this->graph->get($params->friend_uid) : elgg_get_logged_in_user_entity();

		if (!$user instanceof ElggUser || !$friend instanceof ElggUser) {
			throw new GraphException("User or friend not found", HttpResponse::HTTP_NOT_FOUND);
		}

		if (!$user->canEdit()) {
			throw new GraphException("You are not allowed to modify this user's friends list", HttpResponse::HTTP_FORBIDDEN);
		}

		if ($user->guid == $friend->guid) {
			throw new GraphException("You are trying to friend yourself", HttpResponse::HTTP_BAD_REQUEST);
		}

		if (check_entity_relationship($user->guid, 'friend', $friend->guid)) {
			throw new GraphException("Already a friend", HttpResponse::HTTP_BAD_REQUEST);
		}

		if (!add_entity_relationship($user->guid, 'friend', $friend->guid)) {
			throw new GraphException("Unable to create friendship");
		}

		$river_id = elgg_create_river_item(array(
			'view' => 'river/relationship/friend/create',
			'action_type' => 'friend',
			'subject_guid' => $user->guid,
			'object_guid' => $friend->guid,
		));

		$return = array(
			'nodes' => array(
				'friend' => check_entity_relationship($user->guid, 'friend', $friend->guid),
				'friend_of' => check_entity_relationship($friend->guid, 'friend', $user->guid),
			),
		);

		if (!empty($river_id)) {
			$river = elgg_get_river(array(
				'ids' => $river_id,
			));
			$return['nodes']['activity'] = ($river) ? $river[0] : $river_id;
		}

		return $return;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete(ParameterBag $params) {

		$user = get_entity($params->guid);
		$friend = $params->friend_uid ? $this->graph->get($params->friend_uid) : elgg_get_logged_in_user_entity();

		if (!$user instanceof ElggUser || !$friend instanceof ElggUser) {
			throw new GraphException("User or friend not found", HttpResponse::HTTP_NOT_FOUND);
		}

		if (!$user->canEdit()) {
			throw new GraphException("You are not allowed to modify this user's friends list", HttpResponse::HTTP_FORBIDDEN);
		}
		
		if (!remove_entity_relationship($user->guid, "friend", $friend->guid)) {
			throw new GraphException("Unable to remove friendship");
		}

		return array(
			'nodes' => array(
				'friend' => check_entity_relationship($user->guid, 'friend', $friend->guid),
				'friend_of' => check_entity_relationship($friend->guid, 'friend', $user->guid),
			),
		);
	}

}
