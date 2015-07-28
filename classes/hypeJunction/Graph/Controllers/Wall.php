<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;
use hypeJunction\Wall\Actions\SavePost;
use hypeJunction\Wall\Post;

class Wall extends Object {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_PUT :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new HiddenParameter('container_guid', false, Parameter::TYPE_INT),
					new Parameter('status', false),
					new Parameter('address', false),
					new Parameter('location', false),
					new Parameter('friend_uids', false, Parameter::TYPE_ARRAY),
					new Parameter('upload_uids', false, Parameter::TYPE_ARRAY),
					new Parameter('attachment_uids', false, Parameter::TYPE_ARRAY),
					new Parameter('tags', false, Parameter::TYPE_STRING),
					new Parameter('access_id', false, Parameter::TYPE_INT),
				);

			default :
				return parent::params($method);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function put(ParameterBag $params) {

		$action = new SavePost();
		$action->post = get_entity($params->guid);
		$action->poster = $action->post ? $action->post->getOwnerEntity() : elgg_get_logged_in_user_entity();
		$action->container = $action->post ? $action->post->getContainerEntity() : $action->poster;
		$action->subtype = Post::SUBTYPE;

		if ($action->post) {
			foreach (array('status', 'address', 'location', 'access_id', 'tags') as $key) {
				if ($params->$key === null) {
					$params->$key = $action->post->$key;
				}
			}
		}
		
		$action->status = $params->status;
		$action->address = $params->address;
		$action->location = $params->location;
		$action->tags = $params->tags;

		$action->friend_guids = array();
		$action->upload_guids = array();
		$action->attachment_guids = array();
		$action->make_bookmark = $params->make_bookmark && !$action->post;

		$action->access_id = isset($params->access_id) ? $params->access_id : get_default_access($action->poster);

		$friend_uids = (array) $params->friend_uids;
		foreach ($friend_uids as $uid) {
			$action->friend_guids[] = $this->graph->get($uid)->guid;
		}

		$attachment_uids = (array) $params->attachment_uids;
		foreach ($attachment_uids as $uid) {
			$action->attachment_guids[] = $this->graph->get($uid)->guid;
		}

		$upload_uids = (array) $params->upload_uids;
		foreach ($upload_uids as $uid) {
			$upload = $this->graph->get($uid);
			if ($upload && $upload->origin == 'graph' && $upload->access_id == ACCESS_PRIVATE) {
				$action->upload_guids[] = $upload->guid;
			} else {
				hypeGraph()->logger->log("Can not use node $uid as upload. Only resources uploaded via Graph API with private access can be attached.", "ERROR");
			}
		}

		try {
			if ($action->validate() !== false) {
				$action->execute();
			}
			if (!$action->post) {
				throw new \Exception(implode(', ', $action->getResult()->getErrors()));
			}
		} catch (\Exception $ex) {
			throw new GraphException($ex->getMessage());
		}

		return ['nodes' => [$action->post, $action->river, $action->bookmark]];
	}

}
