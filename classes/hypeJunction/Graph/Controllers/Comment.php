<?php

namespace hypeJunction\Graph\Controllers;

use ElggGroup;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class Comment extends Controller {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_GET :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
				);

			case HttpRequest::METHOD_PUT :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('comment', true),
				);

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
	public function delete(ParameterBag $params) {
		$comment = get_entity($params->guid);
		if (!$comment || !$comment->canDelete()) {
			throw new GraphException('You are not permitted to delete this comment', 403);
		}
		return $comment->delete();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		return array('nodes' => array(get_entity($params->guid)));
	}

	/**
	 * {@inheritdoc}
	 */
	public function put(ParameterBag $params) {

		$comment = get_entity($params->guid);
		if (!$comment || !$comment->canEdit()) {
			throw new GraphException('You are not permitted to edit this comment', 403);
		}

		$comment->description = $params->comment;
		if (!$comment->save()) {
			throw new GraphException('Your comment can not be saved');
		}

		return $this->get($params);
	}

}
