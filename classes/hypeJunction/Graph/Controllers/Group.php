<?php

namespace hypeJunction\Graph\Controllers;

use ElggGroup;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class Group extends Controller {

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
				$params = array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new HiddenParameter('owner_guid', false, Parameter::TYPE_INT),
					new Parameter('name', false, Parameter::TYPE_STRING, null, null, elgg_echo('groups:name')),
					new Parameter('membership', false, Parameter::TYPE_ENUM, null, array((string) ACCESS_PRIVATE, (string) ACCESS_PUBLIC), elgg_echo('groups:membership')),
					new Parameter('vis', false, Parameter::TYPE_ENUM, null, array((string) ACCESS_PRIVATE, (string) ACCESS_PUBLIC, (string) ACCESS_LOGGED_IN), elgg_echo('groups:visibility')),
					new Parameter('content_access_mode', false, Parameter::TYPE_ENUM, null, array(ElggGroup::CONTENT_ACCESS_MODE_UNRESTRICTED, ElggGroup::CONTENT_ACCESS_MODE_MEMBERS_ONLY), elgg_echo('groups:content_access_mode')),
				);
				foreach (elgg_get_config('group') as $shortname => $valuetype) {
					$params[] = new Parameter($shortname, false, Parameter::TYPE_STRING, null, null, elgg_echo("groups:$shortname"));
				}
				$tool_options = elgg_get_config('group_tool_options');
				if ($tool_options) {
					foreach ($tool_options as $group_option) {
						$option_toggle_name = $group_option->name . "_enable";
						$option_default = $group_option->default_on ? 'yes' : 'no';
						$params[] = new Parameter($option_toggle_name, false, Parameter::TYPE_STRING, null, array('yes', 'no'), $group_option->label);
					}
				}
				return $params;

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
		$group = get_entity($params->guid);
		if (!$group || !$group->canDelete()) {
			throw new GraphException('You are not permitted to delete this user', 403);
		}
		return $group->delete();
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

		hypeGraph()->logger->vardump('params', $params);
		
		$user = isset($params->owner_guid) && $params->owner_guid ? get_entity($params->owner_guid) : elgg_get_logged_in_user_entity();

		$group_guid = isset($params->guid) ? $params->guid : 0; // allows us to recycle this method from SiteGroups controller
		$is_new_group = $group_guid == 0;

		if ($is_new_group && (elgg_get_plugin_setting('limited_groups', 'groups') == 'yes') && !$user->isAdmin()) {
			throw new GraphException(elgg_echo("groups:cantcreate"), 403);
		}

		$group = $group_guid ? get_entity($group_guid) : new ElggGroup();
		if (elgg_instanceof($group, "group") && !$group->canEdit()) {
			throw new GraphException(elgg_echo("groups:cantedit"), 403);
		}

		if (!$is_new_group) {
			foreach ($params as $key => $value) {
				if ($value === null) {
					$params->$key = $group->$key;
				}
			}
		}

		$input = array();

		foreach (elgg_get_config('group') as $shortname => $valuetype) {
			$input[$shortname] = $params->$shortname;

			if (is_array($input[$shortname])) {
				array_walk_recursive($input[$shortname], function(&$v) {
					$v = _elgg_html_decode($v);
				});
			} else {
				$input[$shortname] = _elgg_html_decode($input[$shortname]);
			}

			if ($valuetype == 'tags') {
				$input[$shortname] = string_to_tag_array($input[$shortname]);
			}
		}

		$input = array_filter($input);

		$input['name'] = htmlspecialchars(get_input('name', '', false), ENT_QUOTES, 'UTF-8');

		// Assume we can edit or this is a new group
		if (sizeof($input) > 0) {
			foreach ($input as $shortname => $value) {
				// update access collection name if group name changes
				if (!$is_new_group && $shortname == 'name' && $value != $group->name) {
					$group_name = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
					$ac_name = sanitize_string(elgg_echo('groups:group') . ": " . $group_name);
					$acl = get_access_collection($group->group_acl);
					if ($acl) {
						// @todo Elgg api does not support updating access collection name
						$db_prefix = elgg_get_config('dbprefix');
						$query = "UPDATE {$db_prefix}access_collections SET name = '$ac_name'
					WHERE id = $group->group_acl";
						update_data($query);
					}
				}

				if ($value === '') {
					// The group profile displays all profile fields that have a value.
					// We don't want to display fields with empty string value, so we
					// remove the metadata completely.
					$group->deleteMetadata($shortname);
					continue;
				}

				$group->$shortname = $value;
			}
		}

		// Validate create
		if (!$group->name) {
			throw new GraphException(elgg_echo("groups:notitle"), 400);
		}

		// Set group tool options
		$tool_options = elgg_get_config('group_tool_options');
		if ($tool_options) {
			foreach ($tool_options as $group_option) {
				$option_toggle_name = $group_option->name . "_enable";
				$option_default = $group->$option_toggle_name ? : $group_option->default_on ? 'yes' : 'no';
				$group->$option_toggle_name = $params->$option_toggle_name ? : $option_default;
			}
		}

		// Group membership - should these be treated with same constants as access permissions?
		$is_public_membership = (int) $params->membership == ACCESS_PUBLIC;
		$group->membership = $is_public_membership ? ACCESS_PUBLIC : ACCESS_PRIVATE;

		$group->setContentAccessMode($params->content_access_mode);

		if ($is_new_group) {
			$group->owner_guid = $user->guid;
			$group->access_id = ACCESS_PUBLIC;
		}

		if ($is_new_group) {
			// if new group, we need to save so group acl gets set in event handler
			if (!$group->save()) {
				throw new GraphException(elgg_echo("groups:save_error"));
			}
		}

		if (elgg_get_plugin_setting('hidden_groups', 'groups') == 'yes') {
			$visibility = (int) $params->vis;

			if ($visibility == ACCESS_PRIVATE) {
				// Make this group visible only to group members. We need to use
				// ACCESS_PRIVATE on the form and convert it to group_acl here
				// because new groups do not have acl until they have been saved once.
				$visibility = $group->group_acl;

				// Force all new group content to be available only to members
				$group->setContentAccessMode(ElggGroup::CONTENT_ACCESS_MODE_MEMBERS_ONLY);
			}

			$group->access_id = $visibility;
		}

		if (!$group->save()) {
			throw new GraphException(elgg_echo("groups:save_error"));
		}

		$river_id = false;
		if ($is_new_group) {
			elgg_set_page_owner_guid($group->guid);

			$group->join($user);
			$river_id = elgg_create_river_item(array(
				'view' => 'river/group/create',
				'action_type' => 'create',
				'subject_guid' => $user->guid,
				'object_guid' => $group->guid,
			));
		}

		$return = array(
			'nodes' => array(
				'group' => $group
			),
		);

		if ($river_id) {
			$river = elgg_get_river(array(
				'ids' => $river_id,
			));
			$return['nodes']['activity'] = ($river) ? $river[0] : $river_id;
		}

		return $return;
	}

}
