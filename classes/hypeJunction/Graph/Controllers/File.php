<?php

namespace hypeJunction\Graph\Controllers;

use ElggFile;
use hypeJunction\Filestore\IconHandler;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\HttpResponse;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class File extends Object {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_GET :
				$params = parent::params($method);
				$params[] = new Parameter('raw', false, Parameter::TYPE_BOOL, false, null, elgg_echo('graph:file:raw'));
				return $params;

			case HttpRequest::METHOD_PUT :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new HiddenParameter('owner_guid', false, Parameter::TYPE_INT),
					new HiddenParameter('container_guid', false, Parameter::TYPE_INT),
					new Parameter('title', false, Parameter::TYPE_STRING, null, null, elgg_echo('title')),
					new Parameter('description', false, Parameter::TYPE_STRING, null, null, elgg_echo('description')),
					new Parameter('tags', false, Parameter::TYPE_STRING, null, null, elgg_echo('tags')),
					new Parameter('access_id', false, Parameter::TYPE_INT, ACCESS_PRIVATE),
					new Parameter('filename', false, Parameter::TYPE_STRING, null, null, elgg_echo('graph:file:filename')),
					new Parameter('contents', false, Parameter::TYPE_STRING, null, null, elgg_echo('graph:file:contents')),
					new Parameter('mimetype', false, Parameter::TYPE_STRING, null, null, elgg_echo('graph:file:mimetype')),
					new Parameter('checksum', false, Parameter::TYPE_STRING, null, null, elgg_echo('graph:file:checksum')),
				);

			default :
				return parent::params($method);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		if (empty($params->raw)) {
			return parent::get($params);
		}
		
		$file = get_entity($params->guid);
		/* @var $file \ElggFile */

		$file->open('read');
		$response = new HttpResponse($file->grabFile());
		$d = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->originalfilename);
		$response->headers->set('Content-Disposition', $d);
		$response->headers->set('Content-Type', $file->getMimeType());
		$file->close();

		return $response;
	}

	/**
	 * {@inheritdoc}
	 */
	public function put(ParameterBag $params) {

		$decoded = "";
		if ($params->contents) {
			if (empty($params->filename) || empty($params->mimetype)) {
				throw new GraphException("You need to provide a filename and content type with encoded file contents", HttpResponse::HTTP_BAD_REQUEST);
			}
			for ($i = 0; $i < ceil(strlen($params->contents) / 256); $i++) {
				$decoded = $decoded . base64_decode(substr($params->contents, $i * 256, 256));
			}
			if (!$decoded) {
				throw new GraphException("File contents can not be empty and must be encoded with base64", HttpResponse::HTTP_BAD_REQUEST);
			}
			if (empty($params->checksum) || md5($decoded) != $params->checksum) {
				throw new GraphException("Checksum mismatch", HttpResponse::HTTP_BAD_REQUEST);
			}
		}

		$owner_guid = $params->owner_guid ? : elgg_get_logged_in_user_guid();
		$owner = get_entity($owner_guid);
		if (!$owner->canEdit()) {
			throw new GraphException("You are not allowed to upload files on users's behalf", HttpResponse::HTTP_FORBIDDEN);
		}

		$container_guid = $params->container_guid ? : elgg_get_logged_in_user_guid();
		$container = get_entity($container_guid);
		if (!$container->canWriteToContainer($owner->guid, 'object', 'file')) {
			throw new GraphException("You are not allowed to upload files to this container", HttpResponse::HTTP_FORBIDDEN);
		}

		$file_guid = $params->guid ? : 0;

		if ($file_guid) {
			$file = get_entity($file_guid);
		} else if ($decoded) {
			$file = new ElggFile();
			$file->subtype = 'file';
			$file->owner_guid = $owner->guid;
			$file->container_guid = $container->guid;
			$file->title = $params->title ? : $params->filename;
			$file->access_id = ACCESS_PRIVATE;
			$file->origin = 'graph';
		}

		$attrs = array('title', 'description', 'access_id');
		foreach ($attrs as $attr) {
			if (isset($params->$attr) && $this->request->get($attr) !== null) {
				$file->$attr = $params->$attr;
			}
		}

		if (!$file instanceof \ElggFile) {
			throw new GraphException("Unable to load or create a file entity");
		}

		if ($decoded) {
			$file->setFilename("file/{$params->filename}");
			if (!$file->exists()) {
				$file->open('write');
				$file->close();
			}

			file_put_contents($file->getFilenameOnFilestore(), $decoded);

			$file->originalfilename = $params->filename;
			$mimetype = $file->detectMimeType(null, $params->mimetype);
			$file->setMimeType($mimetype);
			$file->simpletype = elgg_get_file_simple_type($mimetype);
		}

		$guid = $file->save();

		if (!$guid) {
			$file->delete();
			throw new GraphException("File could not be saved with given parameters", HttpResponse::HTTP_BAD_REQUEST);
		}

		if ($file->simpletype == 'image') {
			IconHandler::makeIcons($file);
		}

		return array('nodes' => array($file));
	}

}
