<?php

namespace hypeJunction\Graph\Controllers;

use ElggComment;
use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class ObjectComments extends Controller {

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
					new Parameter('comment', true, Parameter::TYPE_STRING),
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
			'types' => 'object',
			'subtypes' => 'comment',
			'container_guids' => sanitize_int($params->guid),
			'limit' => $params->limit,
			'offset' => $params->offset,
			'sort' => $params->sort,
			'preload_owners' => true,
		);
		return new BatchResult('elgg_get_entities', $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {

		$user = elgg_get_logged_in_user_entity();
		$object = get_entity($params->guid);

		if (!$object || !$object->canWriteToContainer(0, 'object', 'comment')) {
			throw new GraphException("You are not allowed to comment on this object", 403);
		}

		$comment_text = $params->comment;

		$comment = new ElggComment();
		$comment->owner_guid = $user->guid;
		$comment->container_guid = $object->guid;
		$comment->description = $comment_text;
		$comment->access_id = $object->access_id;

		if (!$comment->save()) {
			throw new GraphException(elgg_echo("generic_comment:failure"));
		}

		// Notify if poster wasn't owner
		if ($object->owner_guid != $user->guid) {
			$owner = $object->getOwnerEntity();

			notify_user($owner->guid, $user->guid, elgg_echo('generic_comment:email:subject', array(), $owner->language), elgg_echo('generic_comment:email:body', array(
				$object->title,
				$user->name,
				$comment->description,
				$comment->getURL(),
				$user->name,
				$user->getURL()
							), $owner->language), array(
				'object' => $comment,
				'action' => 'create',
					)
			);
		}

		$return = array(
			'nodes' => array(
				'comment' => $comment,
			)
		);
		
		// Add to river
		$river_id = elgg_create_river_item(array(
			'view' => 'river/object/comment/create',
			'action_type' => 'comment',
			'subject_guid' => $user->guid,
			'object_guid' => $comment->guid,
			'target_guid' => $object->guid,
		));

		if ($river_id) {
			$river = elgg_get_river(array(
				'ids' => $river_id,
			));
			$return['nodes']['activity'] = ($river) ? $river[0] : $river_id;
		}

		return $return;
	}

}
