<?php

namespace hypeJunction\Graph;

use ElggEntity;
use ElggExtender;
use ElggGroup;
use ElggObject;
use ElggRelationship;
use ElggRiverItem;
use ElggSite;
use ElggUser;
use Exception;
use stdClass;

/**
 * Abstract API Resource
 */
class Graph {

	const LIMIT_MAX = 50;

	/**
	 * Returns an array of aliases for Elgg entities and extenders
	 * @return array
	 */
	public function getAliases() {
		$aliases = array(
			'user' => ':user',
			'site' => ':site',
			'group' => ':group',
			'object' => array(
				'comment' => ':comment',
			),
			'river' => array(
				'item' => ':activity',
			),
			'annotation' => array(
				'likes' => ':like',
			),
			'relationship' => array(
				'member' => ':member',
				'membership_request' => ':membership_request',
				'invited' => ':invitation',
				'friend' => ':friend',
			),
		);

		if (elgg_is_active_plugin('blog')) {
			$aliases['object']['blog'] = ':blog';
		}
		if (elgg_is_active_plugin('file')) {
			$aliases['object']['file'] = ':file';
		}
		if (elgg_is_active_plugin('messages') || elgg_is_active_plugin('hypeInbox')) {
			$aliases['object']['messages'] = ':message';
		}
		if (elgg_is_active_plugin('hypeWall')) {
			$aliases['object'][\hypeJunction\Wall\Post::SUBTYPE] = ':wall';
		}
		return elgg_trigger_plugin_hook('aliases', 'graph', null, $aliases);
	}

	/**
	 * Returns allowed fields for a node
	 * 
	 * @param mixed $node Node
	 * @return string
	 */
	public function getFields($node = null) {

		if (!$this->isExportable($node)) {
			return array();
		}

		switch ($node->getType()) {
			case 'user' :
				$fields = array(
					'type',
					'subtype',
					'uid',
					'name',
					'username',
					'briefdescription',
					'banned',
					'validated',
					'admin',
					'url',
					'icon',
					'time_created',
					'enabled',
				);
				break;

			case 'site' :
				$fields = array(
					'type',
					'subtype',
					'uid',
					'name',
					'description',
					'url',
					'email',
					'allow_registration',
					'default_access',
					'debug',
					'walled_garden',
					'time_created',
					'enabled',
				);
				break;

			case 'object' :
				$fields = array(
					'type',
					'subtype',
					'uid',
					'title',
					'description',
					'url',
					'icon',
					'owner',
					'container',
					'access',
					'time_created',
					'enabled',
					'tags',
				);

				switch ($node->getSubtype()) {
					case 'blog' :
						$fields[] = 'status';
						$fields[] = 'comments_on';
						$fields[] = 'excerpt';
						break;

					case 'file' :
						$fields[] = 'simpletype';
						$fields[] = 'mimetype';
						$fields[] = 'originalfilename';
						$fields[] = 'origin';
						break;

					case 'messages' :
						foreach (array('owner', 'container', 'tags', 'icon') as $needle) {
							$key = array_search($needle, $fields);
							unset($fields[$key]);
						}
						$fields[] = 'status';
						$fields[] = 'sender';
						$fields[] = 'recipients';
						if (elgg_is_active_plugin('hypeInbox')) {
							$fields[] = 'thread_id';
							$fields[] = 'message_type';
							$fields[] = 'attachments';
						}
						break;

					case \hypeJunction\Wall\Post::SUBTYPE :
						$fields[] = 'location';
						$fields[] = 'address';
						$fields[] = 'tagged_users';
						$fields[] = 'attachments';
						break;
				}
				break;

			case 'group' :
				$fields = array(
					'type',
					'subtype',
					'uid',
					'name',
					'url',
					'icon',
					'briefdescription',
					'owner',
					'container',
					'content_access_mode',
					'membership',
					'access',
					'group_acl',
					'time_created',
					'enabled',
				);
				break;

			case 'metadata' :
			case 'annotation' :
				$fields = array(
					'type',
					'subtype',
					'uid',
					'value',
					'entity',
					'owner',
					'access',
					'time_created',
					'enabled',
				);
				break;

			case 'river' :
				$fields = array(
					'type',
					'subtype',
					'uid',
					'action',
					'subject',
					'object',
					'target',
					'annotation',
					'access',
					'time_created',
					'enabled',
				);
				break;

			case 'relationship' :
				$fields = array(
					'type',
					'subtype',
					'uid',
					'subject',
					'object',
					'time_created',
				);
				break;
		}

		if (!$this->getAlias($node)) {
			$fields = array('type', 'subtype', 'uid', 'url', 'time_created', 'enabled', 'access');
		}

		return elgg_trigger_plugin_hook('fields', 'graph', ['node' => $node], $fields);
	}

	/**
	 * Returns an alias for a node
	 *
	 * @param mixed $node Node
	 * @return string|false
	 */
	public function getAlias($node = null) {
		if (!$this->isExportable($node)) {
			return false;
		}
		$type = $node->getType();
		$subtype = $node->getSubtype();

		$types = elgg_extract($type, $this->getAliases());
		if (is_string($types) && !$subtype) {
			return $types;
		}

		return elgg_extract($subtype, (array) $types, false);
	}

	/**
	 * Returns a node from it's uid
	 *
	 * @param string $uid UID of the resource
	 * @return mixed
	 */
	public function get($uid = '') {

		switch ($uid) {
			case 'me' :
				$uid = "ue" . elgg_get_logged_in_user_guid();
				break;

			case 'site' :
				$uid = "se" . elgg_get_site_entity()->guid;
				break;
		}

		if (substr($uid, 0, 2) == 'an') {
			$id = (int) substr($uid, 2);
			$node = elgg_get_annotation_from_id($id);
		} else if (substr($uid, 0, 2) == 'md') {
			$id = (int) substr($uid, 2);
			$node = elgg_get_metadata_from_id($id);
		} else if (substr($uid, 0, 2) == 'rl') {
			$id = (int) substr($uid, 2);
			$node = get_relationship($id);
		} else if (substr($uid, 0, 2) == 'rv') {
			$id = (int) substr($uid, 2);
			$river = elgg_get_river(array(
				'ids' => sanitize_int($id),
			));
			$node = $river ? $river[0] : false;
		} else if (in_array(substr($uid, 0, 2), array('ue', 'se', 'oe', 'ge'))) {
			$id = (int) substr($uid, 2);
			$node = get_entity($id);
		} else if (is_numeric($uid)) {
			$node = get_entity($uid);
		} else {
			$node = get_user_by_username($uid);
		}

		if (!$this->isExportable($node)) {
			return false;
		}

		return $node;
	}

	/**
	 * Site export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 */
	public function exportSite($hook, $type, $return, $params) {
		if (!elgg_in_context('graph')) {
			return $return;
		}

		$entity = elgg_extract('entity', $params);
		if (!$entity instanceof ElggSite) {
			return $return;
		}

		$allowed = $this->getFields($entity);
		$fields = get_input('fields') ? : $allowed;
		if (is_string($fields)) {
			$fields = string_to_tag_array($fields);
		}

		$result = array();

		foreach ($fields as $key) {
			if (!in_array($key, $allowed)) {
				$result[$key] = ELGG_ENTITIES_ANY_VALUE;
				continue;
			}

			switch ($key) {
				default :
					$result[$key] = $entity->$key;
					break;

				case 'uid' :
					$result[$key] = "se{$entity->guid}";
					break;

				case 'type' :
					$result[$key] = $entity->getType();
					break;

				case 'subtype' :
					$result[$key] = $entity->getSubtype();
					break;

				case 'time_created' :
				case 'time_updated' :
					$result[$key] = date(DATE_ATOM, $entity->$key);
					break;

				case 'url' :
					$result[$key] = $entity->getURL();
					break;

				case 'allow_registration' :
				case 'default_access' :
				case 'debug' :
				case 'walled_garden' :
					$result[$key] = elgg_get_config($key);
					break;
			}
		}

		return (object) $result;
	}

	/**
	 * User export
	 * 
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 */
	public function exportUser($hook, $type, $return, $params) {

		if (!elgg_in_context('graph')) {
			return $return;
		}

		$entity = elgg_extract('entity', $params);
		if (!$entity instanceof ElggUser) {
			return $return;
		}

		$allowed = $this->getFields($entity);
		$fields = get_input('fields') ? : $allowed;
		if (is_string($fields)) {
			$fields = string_to_tag_array($fields);
		}

		$result = array();

		foreach ($fields as $key) {
			if (!in_array($key, $allowed)) {
				$result[$key] = ELGG_ENTITIES_ANY_VALUE;
				continue;
			}

			switch ($key) {
				default :
					$result[$key] = $entity->$key;
					break;

				case 'uid' :
					$result[$key] = "ue{$entity->guid}";
					break;

				case 'type' :
					$result[$key] = $entity->getType();
					break;

				case 'subtype' :
					$result[$key] = $entity->getSubtype();
					break;

				case 'time_created' :
				case 'time_updated' :
					$result[$key] = date(DATE_ATOM, $entity->$key);
					break;

				case 'url' :
					$result[$key] = $entity->getURL();
					break;

				case 'icon' :
					$result['icon'] = array();
					$icon_sizes = array_keys((array) elgg_get_config('icon_sizes'));
					foreach ($icon_sizes as $size) {
						$result['icon'][$size] = $entity->getIconURL($size);
					}
					break;

				case 'banned' :
					$result[$key] = $entity->isBanned();
					break;

				case 'admin' :
					$result[$key] = $entity->isAdmin();
					break;
			}
		}

		return (object) $result;
	}

	/**
	 * Object export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 */
	public function exportObject($hook, $type, $return, $params) {

		if (!elgg_in_context('graph')) {
			return $return;
		}

		$entity = elgg_extract('entity', $params);
		if (!$entity instanceof ElggObject) {
			return $return;
		}

		$allowed = $this->getFields($entity);
		$fields = get_input('fields') ? : $allowed;
		if (is_string($fields)) {
			$fields = string_to_tag_array($fields);
		}

		$result = array();

		foreach ($fields as $key) {
			if (!in_array($key, $allowed)) {
				$result[$key] = ELGG_ENTITIES_ANY_VALUE;
				continue;
			}

			switch ($key) {
				default :
					$result[$key] = $entity->$key;
					break;

				case 'uid' :
					$result[$key] = "oe{$entity->guid}";
					break;

				case 'type' :
					$result[$key] = $entity->getType();
					break;

				case 'subtype' :
					$result[$key] = $entity->getSubtype();
					break;

				case 'time_created' :
				case 'time_updated' :
					$result[$key] = date(DATE_ATOM, $entity->$key);
					break;

				case 'url' :
					$result[$key] = $entity->getURL();
					break;

				case 'icon' :
					$result['icon'] = array();
					$icon_sizes = array_keys((array) elgg_get_config('icon_sizes'));
					foreach ($icon_sizes as $size) {
						$result['icon'][$size] = $entity->getIconURL($size);
					}
					break;

				case 'owner' :
					$owner = $entity->getOwnerEntity();
					$result[$key] = ($owner) ? $owner->toObject() : null;
					break;

				case 'container' :
					$container = $entity->getContainerEntity();
					$result[$key] = ($container) ? $container->toObject() : null;
					break;

				case 'access' :
					$result[$key] = array(
						'id' => $entity->access_id,
						'label' => get_readable_access_level($entity->access_id),
					);
					break;

				case 'tags' :
					$result[$key] = (array) $entity->tags;
					break;
			}
		}

		return (object) $result;
	}

	/**
	 * Message export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 */
	public function exportMessage($hook, $type, $return, $params) {

		if (!elgg_in_context('graph')) {
			return $return;
		}

		$entity = elgg_extract('entity', $params);
		if (!$entity instanceof ElggObject || $entity->getSubtype() !== 'messages') {
			return $return;
		}

		$allowed = $this->getFields($entity);
		$fields = get_input('fields') ? : $allowed;
		if (is_string($fields)) {
			$fields = string_to_tag_array($fields);
		}

		foreach ($fields as $key) {
			switch ($key) {

				case 'status' :
					$return->status = ($entity->readYet) ? 'read' : 'unread';
					break;

				case 'sender' :
					$sender = get_entity($entity->fromId);
					if ($sender) {
						$return->sender = $sender->toObject();
					}
					break;

				case 'recipients' :
					$return->recipients = array();
					$recipients = new \ElggBatch('elgg_get_entities', array(
						'guids' => $entity->toId,
						'limit' => 0,
						'order_by' => 'e.guid',
					));
					foreach ($recipients as $user) {
						$return->recipients[] = $user->toObject();
					}
					break;

				case 'attachments' :
					$return->attachments = array();
					$attachments = new \ElggBatch('elgg_get_entities_from_relationship', array(
						'relationship' => 'attached',
						'relationship_guid' => $this->guid,
						'inverse_relationship' => false,
					));
					foreach ($attachments as $attachment) {
						$return->attachments[] = $attachment->toObject();
					}
					break;

				case 'thread_id' :
					$return->thread_id = $entity->msgHash;
					break;

				case 'message_type' :
					$return->message_type = $entity->msgType;
					break;
			}
		}

		return $return;
	}

	/**
	 * Wall post export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 */
	public function exportWall($hook, $type, $return, $params) {

		if (!elgg_in_context('graph')) {
			return $return;
		}

		$entity = elgg_extract('entity', $params);
		/* @var $entity \hypeJunction\Wall\Post */

		if (!$entity instanceof ElggObject || $entity->getSubtype() !== \hypeJunction\Wall\Post::SUBTYPE) {
			return $return;
		}

		$allowed = $this->getFields($entity);
		$fields = get_input('fields') ? : $allowed;
		if (is_string($fields)) {
			$fields = string_to_tag_array($fields);
		}

		foreach ($fields as $key) {
			switch ($key) {

				case 'attachments' :
					$return->attachments = array();
					$attachments = new \ElggBatch('elgg_get_entities_from_relationship', array(
						'types' => 'object',
						'subtypes' => get_registered_entity_types('object'),
						'relationship' => 'attached',
						'relationship_guid' => $entity->guid,
						'inverse_relationship' => true,
						'limit' => 0,
					));
					foreach ($attachments as $attachment) {
						$return->attachments[] = $attachment->toObject();
					}
					break;

				case 'location' :
					$return->location = $entity->getLocation();
					break;

				case 'address' :
					$return->address = $entity->address;
					if (elgg_is_active_plugin('hypeScraper')) {
						$return->embed = hypeScraper()->resources->get($entity->address);
					}
					break;

				case 'tagged_users' :
					$this->tagged_users = array();
					$users = (array) $entity->getTaggedFriends();
					foreach ($users as $user) {
						if ($user instanceof \ElggUser) {
							$this->tagged_users[] = $user->toObject();
						}
					}
					break;
			}
		}

		return $return;
	}

	/**
	 * Group export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 */
	public function exportGroup($hook, $type, $return, $params) {

		if (!elgg_in_context('graph')) {
			return $return;
		}

		$entity = elgg_extract('entity', $params);
		if (!$entity instanceof ElggGroup) {
			return $return;
		}

		$allowed = $this->getFields($entity);
		$fields = get_input('fields') ? : $allowed;
		if (is_string($fields)) {
			$fields = string_to_tag_array($fields);
		}

		$result = array();

		foreach ($fields as $key) {
			if (!in_array($key, $allowed)) {
				$result[$key] = ELGG_ENTITIES_ANY_VALUE;
				continue;
			}

			switch ($key) {
				default :
					$result[$key] = $entity->$key;
					break;

				case 'uid' :
					$result[$key] = "ge{$entity->guid}";
					break;

				case 'type' :
					$result[$key] = $entity->getType();
					break;

				case 'subtype' :
					$result[$key] = $entity->getSubtype();
					break;

				case 'time_created' :
				case 'time_updated' :
					$result[$key] = date(DATE_ATOM, $entity->$key);
					break;

				case 'url' :
					$result[$key] = $entity->getURL();
					break;

				case 'icon' :
					$result['icon'] = array();
					$icon_sizes = array_keys((array) elgg_get_config('icon_sizes'));
					foreach ($icon_sizes as $size) {
						$result['icon'][$size] = $entity->getIconURL($size);
					}
					break;

				case 'owner' :
					$owner = $entity->getOwnerEntity();
					$result[$key] = ($owner) ? $owner->toObject() : null;
					break;

				case 'container' :
					$container = $entity->getContainerEntity();
					$result[$key] = ($container) ? $container->toObject() : null;
					break;

				case 'content_access_mode' :
					$modes = array(
						ElggGroup::CONTENT_ACCESS_MODE_UNRESTRICTED => elgg_echo("groups:content_access_mode:unrestricted"),
						ElggGroup::CONTENT_ACCESS_MODE_MEMBERS_ONLY => elgg_echo("groups:content_access_mode:membersonly"),
					);
					$result[$key] = array(
						'id' => $entity->$key,
						'label' => $modes[$entity->$key],
					);
					break;

				case 'membership' :
					$options = array(
						ACCESS_PRIVATE => elgg_echo("groups:access:private"),
						ACCESS_PUBLIC => elgg_echo("groups:access:public"),
					);
					$result[$key] = array(
						'id' => $entity->$key,
						'label' => $options[$entity->$key],
					);
					break;

				case 'group_acl' :
					$result[$key] = array(
						'id' => $entity->$key,
						'label' => get_readable_access_level($entity->$key),
					);
					break;

				case 'access' :
					$result[$key] = array(
						'id' => $entity->access_id,
						'label' => get_readable_access_level($entity->access_id),
					);
					break;
			}
		}

		return (object) $result;
	}

	/**
	 * River export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "river_item"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 */
	public function exportRiver($hook, $type, $return, $params) {
		if (!elgg_in_context('graph')) {
			return $return;
		}

		$entity = elgg_extract('item', $params);
		if (!$entity instanceof ElggRiverItem) {
			return $return;
		}

		$allowed = $this->getFields($entity);
		$fields = get_input('fields') ? : $allowed;
		if (is_string($fields)) {
			$fields = string_to_tag_array($fields);
		}

		$result = array();

		foreach ($fields as $key) {
			if (!in_array($key, $allowed)) {
				$result[$key] = ELGG_ENTITIES_ANY_VALUE;
				continue;
			}

			switch ($key) {
				default :
					$result[$key] = $entity->$key;
					break;

				case 'uid' :
					$result[$key] = "rv{$entity->id}";
					break;

				case 'action' :
					$result[$key] = $entity->action_type;
					break;

				case 'type' :
					$result[$key] = $entity->getType();
					break;

				case 'subtype' :
					$result[$key] = $entity->getSubtype();
					break;

				case 'time_created' :
					$result[$key] = date(DATE_ATOM, $entity->posted);
					break;

				case 'subject' :
					$subject = $entity->getSubjectEntity();
					$result[$key] = ($subject) ? $subject->toObject() : null;
					break;

				case 'object' :
					$object = $entity->getObjectEntity();
					$result[$key] = ($object) ? $object->toObject() : null;
					break;

				case 'target' :
					$target = $entity->getTargetEntity();
					$result[$key] = ($target) ? $target->toObject() : null;
					break;

				case 'annotation' :
					if ($entity->annotation_id) {
						$annotation = elgg_get_annotation_from_id($entity->annotation_id);
						if ($annotation && $this->getAlias($annotation)) {
							$result[$key] = $annotation->toObject();
						}
					}
					$result[$key] = $entity->annotation_id;
					break;

				case 'access' :
					$result[$key] = array(
						'id' => $entity->access_id,
						'label' => get_readable_access_level($entity->access_id),
					);
					break;
			}
		}

		return (object) $result;
	}

	/**
	 * Relationship export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "relationship"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 */
	public function exportRelationship($hook, $type, $return, $params) {
		if (!elgg_in_context('graph')) {
			return $return;
		}

		$entity = elgg_extract('relationship', $params);
		if (!$entity instanceof ElggRelationship) {
			return $return;
		}

		$allowed = $this->getFields($entity);
		$fields = get_input('fields') ? : $allowed;
		if (is_string($fields)) {
			$fields = string_to_tag_array($fields);
		}

		$result = array();

		foreach ($fields as $key) {
			if (!in_array($key, $allowed)) {
				$result[$key] = ELGG_ENTITIES_ANY_VALUE;
				continue;
			}

			switch ($key) {
				default :
					$result[$key] = $entity->$key;
					break;

				case 'uid' :
					$result[$key] = "rl{$entity->id}";
					break;

				case 'type' :
					$result[$key] = $entity->getType();
					break;

				case 'subtype' :
					$result[$key] = $entity->getSubtype();
					break;

				case 'subject' :
					$subject = get_entity($entity->guid_one);
					$result[$key] = ($subject) ? $subject->toObject() : null;
					break;

				case 'object' :
					$object = get_entity($entity->guid_two);
					$result[$key] = ($object) ? $object->toObject() : null;
					break;

				case 'time_created' :
					$result[$key] = date(DATE_ATOM, $entity->time_created);
					break;
			}
		}

		return (object) $result;
	}

	/**
	 * Extender export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   Metadata or annotation name
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 */
	public function exportExtender($hook, $type, $return, $params) {
		if (!elgg_in_context('graph')) {
			return $return;
		}

		$aliases = $this->getAliases();
		$annotation_names = (array) elgg_extract('annotation', $aliases, array());
		$metadata_names = (array) elgg_extract('metadata', $aliases, array());
		$extender_names = array_merge($annotation_names, $metadata_names);

		foreach ($params as $key => $entity) {
			if (in_array($key, $extender_names) && $entity instanceof \ElggExtender) {
				break;
			}
		}

		if (!$entity instanceof ElggExtender) {
			return $return;
		}

		$allowed = $this->getFields($entity);
		$fields = get_input('fields') ? : $allowed;
		if (is_string($fields)) {
			$fields = string_to_tag_array($fields);
		}

		$result = array();

		foreach ($fields as $key) {
			if (!in_array($key, $allowed)) {
				$result[$key] = ELGG_ENTITIES_ANY_VALUE;
				continue;
			}

			switch ($key) {
				default :
					$result[$key] = $entity->$key;
					break;

				case 'uid' :
					if ($entity->getType() == 'metadata') {
						$result[$key] = "md{$entity->id}";
					} else {
						$result[$key] = "an{$entity->id}";
					}
					break;

				case 'type' :
					$result[$key] = $entity->getType();
					break;

				case 'subtype' :
					$result[$key] = $entity->getSubtype();
					break;

				case 'owner' :
					$owner = $entity->getOwnerEntity();
					$result[$key] = ($owner) ? $owner->toObject() : null;
					break;

				case 'entity' :
					$parent = $entity->getEntity();
					$result[$key] = ($parent) ? $parent->toObject() : null;
					break;

				case 'time_created' :
					$result[$key] = date(DATE_ATOM, $entity->time_created);
					break;

				case 'access' :
					$result[$key] = array(
						'id' => $entity->access_id,
						'label' => get_readable_access_level($entity->access_id),
					);
					break;
			}
		}

		return (object) $result;
	}

	/**
	 * Test if node is exportable
	 * 
	 * @param mixed $node Node
	 * @return boolean
	 */
	public function isExportable($node = null) {
		if ($node instanceof ElggEntity) {
			return true;
		} else if ($node instanceof \ElggRelationship) {
			return true;
		} else if ($node instanceof ElggExtender) {
			return true;
		} else if ($node instanceof \ElggRiverItem) {
			return true;
		}
		return false;
	}

	/**
	 * Returns all configured routes
	 * @return array
	 */
	public function exportRoutes() {
		$output = array();
		$request_types = array(
			HttpRequest::METHOD_GET,
			HttpRequest::METHOD_POST,
			HttpRequest::METHOD_PUT,
			HttpRequest::METHOD_DELETE
		);
		$routes = hypeGraph()->router->getRoutes();

		foreach ($routes as $route => $ctrl) {
			try {
				if (!class_exists($ctrl) || !in_array(ControllerInterface::class, class_implements($ctrl))) {
					continue;
				}

				$ctrl = new $ctrl();
				foreach ($request_types as $request_type) {
					$method_parameters = $ctrl->params($request_type);
					if (!is_array($method_parameters) || !is_callable(array($ctrl, strtolower($request_type)))) {
						continue;
					}
					$visible_parameters = array_filter($method_parameters, function($pe) {
						return !$pe instanceof HiddenParameter;
					});
					$parameters = array_map(function($pe) {
						if ($pe instanceof Parameter) {
							return $pe->toArray();
						}
					}, $visible_parameters);
					$request = "{$request_type} /{$route}";
					$output[] = array_filter(array(
						'method' => $route,
						'call_method' => $request_type,
						'endpoint' => $request,
						'description' => elgg_echo($request),
						'parameters' => !empty($parameters) ? $parameters : null,
					));
				}
			} catch (Exception $ex) {
				// do nothing
			}
		}
		return $output;
	}

}
