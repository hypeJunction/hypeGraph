<?php

namespace hypeJunction\Graph\Controllers;

use ElggRelationship;
use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\HttpResponse;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class GroupMembers extends Controller {

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
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
				);

			case HttpRequest::METHOD_DELETE :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('relationship', true, Parameter::TYPE_ENUM, 'member', array('member', 'membership_request', 'invited')),
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
			'relationship' => 'member',
			'relationship_guid' => sanitize_int($params->guid),
			'inverse_relationship' => true,
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

		$user = elgg_get_logged_in_user_entity();
		$group = get_entity($params->guid);

		// join or request
		$join = false;
		if ($group->isPublicMembership() || $group->canEdit($user->guid)) {
			// anyone can join public groups and admins can join any group
			$join = true;
		} else if (check_entity_relationship($group->guid, 'invited', $user->guid)) {
			// user has invite to closed group
			$join = true;
		}

		if ($join) {
			if (groups_join_group($group, $user)) {
				$msg = elgg_echo("groups:joined");
			} else {
				throw new GraphException(elgg_echo("groups:cantjoin"));
			}
		} else {
			if (!add_entity_relationship($user->guid, 'membership_request', $group->guid)) {
				throw new GraphException(elgg_echo("groups:joinrequestnotmade"));
			}

			$owner = $group->getOwnerEntity();

			$url = elgg_normalize_url("groups/requests/$group->guid");

			$subject = elgg_echo('groups:request:subject', array(
				$user->name,
				$group->name,
					), $owner->language);

			$body = elgg_echo('groups:request:body', array(
				$group->getOwnerEntity()->name,
				$user->name,
				$group->name,
				$user->getURL(),
				$url,
					), $owner->language);

			// Notify group owner
			notify_user($owner->guid, $user->getGUID(), $subject, $body);
			$msg = elgg_echo("groups:joinrequestmade");
		}

		return array(
			'nodes' => array(
				'member' => check_entity_relationship($user->guid, 'member', $group->guid),
				'invited' => check_entity_relationship($group->guid, 'invited', $user->guid),
				'membership_request' => check_entity_relationship($user->guid, 'membership_request', $group->guid),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete(ParameterBag $params) {
		$relationship = $params->relationship;
		$user = elgg_get_logged_in_user_entity();
		$group = get_entity($params->guid);

		switch ($relationship) {
			case 'member' :
				$relationship = check_entity_relationship($user->guid, 'member', $group->guid);
				break;
			case 'invited' :
				$relationship = check_entity_relationship($group->guid, 'invited', $user->guid);
				break;
			case 'membership_request' :
				$relationship = check_entity_relationship($user->guid, 'membership_request', $group->guid);
				break;
		}

		if (!$relationship instanceof ElggRelationship) {
			throw new GraphException("Relationship does not exist", HttpResponse::HTTP_NOT_FOUND);
		}

		$relationship->delete();

		return array(
			'nodes' => array(
				'member' => check_entity_relationship($user->guid, 'member', $group->guid),
				'invited' => check_entity_relationship($group->guid, 'invited', $user->guid),
				'membership_request' => check_entity_relationship($user->guid, 'membership_request', $group->guid),
			),
		);
	}

}
