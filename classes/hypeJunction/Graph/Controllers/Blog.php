<?php

namespace hypeJunction\Graph\Controllers;

use ElggBlog;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class Blog extends Object {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_PUT :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new HiddenParameter('container_guid', false, Parameter::TYPE_INT),
					new Parameter('title', false, Parameter::TYPE_STRING, null, null, elgg_echo('title')),
					new Parameter('description', false, Parameter::TYPE_STRING, null, null, elgg_echo('blog:body')),
					new Parameter('excerpt', false, Parameter::TYPE_STRING, null, null, elgg_echo('blog:excerpt')),
					new Parameter('status', false, Parameter::TYPE_ENUM, null, array('draft', 'published'), elgg_echo('status')),
					new Parameter('comments_on', false, Parameter::TYPE_ENUM, null, array('On', 'Off'), elgg_echo('comments')),
					new Parameter('tags', false, Parameter::TYPE_STRING, null, null, elgg_echo('tags')),
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
		$user = elgg_get_logged_in_user_entity();

		$guid = isset($params->guid) ? $params->guid : 0;

		if ($guid) {
			$entity = get_entity($guid);
			if (elgg_instanceof($entity, 'object', 'blog') && $entity->canEdit()) {
				$blog = $entity;
			} else {
				throw new GraphException(elgg_echo('blog:error:post_not_found'), 404);
			}

			// save some data for revisions once we save the new edit
			$revision_text = $blog->description;
			$new_post = $blog->new_post;
		} else {
			$blog = new ElggBlog();
			$blog->subtype = 'blog';
			$new_post = TRUE;
		}

		$old_status = $blog->status;
		$error = false;

		// set defaults and required values.
		$values = array(
			'title' => '',
			'description' => '',
			'status' => 'draft',
			'access_id' => get_default_access(),
			'comments_on' => 'On',
			'excerpt' => '',
			'tags' => '',
			'container_guid' => (int) $params->container_guid,
		);

		// fail if a required entity isn't set
		$required = array('title', 'description');

		// load from POST and do sanity and access checking
		foreach ($values as $name => $default) {
			if (!empty($blog->guid)) {
				$default = $blog->$name;
			}
			// make sure we don't accidentally update the blog with cast non-required value
			$value = isset($params->$name) && $this->request->get($name) ? $params->$name : $default;
			if ($name === 'title') {
				$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
			}

			if (in_array($name, $required) && empty($value)) {
				$error = elgg_echo("blog:error:missing:$name");
			}

			if ($error) {
				break;
			}

			switch ($name) {
				case 'tags':
					$values[$name] = string_to_tag_array($value);
					break;

				case 'excerpt':
					if ($value) {
						$values[$name] = elgg_get_excerpt($value);
					}
					break;

				case 'container_guid':
					// this can't be empty or saving the base entity fails
					if (!empty($value)) {
						if (can_write_to_container($user->getGUID(), $value)) {
							$values[$name] = $value;
						} else {
							$error = elgg_echo("blog:error:cannot_write_to_container");
						}
					} else {
						unset($values[$name]);
					}
					break;

				default:
					$values[$name] = $value;
					break;
			}
		}

		// if draft, set access to private and cache the future access
		if ($values['status'] == 'draft') {
			$values['future_access'] = $values['access_id'];
			$values['access_id'] = ACCESS_PRIVATE;
		}

		// assign values to the entity, stopping on error.
		if (!$error) {
			foreach ($values as $name => $value) {
				$blog->$name = $value;
			}
		}

		// only try to save base entity if no errors
		if ($error) {
			throw new GraphException($error, 400);
		}

		if (!$blog->save()) {
			throw new GraphException(elgg_echo('blog:error:cannot_save'));
		}

		// no longer a brand new post.
		$blog->deleteMetadata('new_post');

		// if this was an edit, create a revision annotation
		if (!$new_post && $revision_text) {
			$blog->annotate('blog_revision', $revision_text);
		}

		$status = $blog->status;

		// add to river if changing status or published, regardless of new post
		// because we remove it for drafts.
		if (($new_post || $old_status == 'draft') && $status == 'published') {
			$river_id = elgg_create_river_item(array(
				'view' => 'river/object/blog/create',
				'action_type' => 'create',
				'subject_guid' => $blog->owner_guid,
				'object_guid' => $blog->getGUID(),
			));

			elgg_trigger_event('publish', 'object', $blog);

			// reset the creation time for posts that move from draft to published
			if ($guid) {
				$blog->time_created = time();
				$blog->save();
			}
		} elseif ($old_status == 'published' && $status == 'draft') {
			elgg_delete_river(array(
				'object_guid' => $blog->guid,
				'action_type' => 'create',
			));
		}

		$return = array(
			'nodes' => array(
				'blog' => $blog
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

}
