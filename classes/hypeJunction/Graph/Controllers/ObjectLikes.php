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

class ObjectLikes extends Controller {

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
				);

			case HttpRequest::METHOD_POST :
			case HttpRequest::METHOD_DELETE :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
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
			'guids' => sanitize_int($params->guid),
			'limit' => $params->limit,
			'offset' => $params->offset,
			'annotation_names' => 'likes',
		);
		return new BatchResult('elgg_get_annotations', $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {

		$entity_guid = (int) $params->guid;

		//check to see if the user has already liked the item
		if (elgg_annotation_exists($entity_guid, 'likes')) {
			throw new GraphException(elgg_echo("likes:alreadyliked"), HttpResponse::HTTP_NOT_MODIFIED);
		}

		// Let's see if we can get an entity with the specified GUID
		$entity = get_entity($entity_guid);
		if (!$entity) {
			throw new GraphException(elgg_echo("likes:notfound"), HttpResponse::HTTP_NOT_FOUND);
		}

		// limit likes through a plugin hook (to prevent liking your own content for example)
		if (!$entity->canAnnotate(0, 'likes')) {
			// plugins should register the error message to explain why liking isn't allowed
			throw new GraphException(elgg_echo("likes:notallowed"), HttpResponse::HTTP_FORBIDDEN);
		}

		$user = elgg_get_logged_in_user_entity();
		$annotation_id = create_annotation($entity->guid, 'likes', "likes", "", $user->guid, $entity->access_id);

		// tell user annotation didn't work if that is the case
		if (!$annotation_id) {
			throw new GraphException(elgg_echo("likes:failure"));
		}

		// notify if poster wasn't owner
		if ($entity->owner_guid != $user->guid) {
			$owner = $entity->getOwnerEntity();

			$annotation = elgg_get_annotation_from_id($annotation_id);

			$title_str = $entity->getDisplayName();
			if (!$title_str) {
				$title_str = elgg_get_excerpt($entity->description);
			}

			$site = elgg_get_site_entity();

			$subject = elgg_echo('likes:notifications:subject', array(
				$user->name,
				$title_str
					), $owner->language
			);

			$body = elgg_echo('likes:notifications:body', array(
				$owner->name,
				$user->name,
				$title_str,
				$site->name,
				$entity->getURL(),
				$user->getURL()
					), $owner->language
			);

			notify_user(
					$entity->owner_guid, $user->guid, $subject, $body, array(
				'action' => 'create',
				'object' => $annotation,
					)
			);
		}

		return array(
			'nodes' => array(
				elgg_get_annotation_from_id($annotation_id),
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete(ParameterBag $params) {
		$likes = elgg_get_annotations(array(
			'guid' => (int) $params->guid,
			'annotation_owner_guid' => elgg_get_logged_in_user_guid(),
			'annotation_name' => 'likes',
		));

		$like = !empty($likes) ? $likes[0] : false;

		if ($like && $like->canEdit()) {
			return $like->delete();
		}

		throw new GraphException(elgg_echo("likes:notdeleted"));
	}

}
