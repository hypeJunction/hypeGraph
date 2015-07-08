<?php

namespace hypeJunction\Graph;

use SecurityException;
use stdClass;

class HmacService {

	/**
	 * Http Request
	 * @var HttpRequest
	 */
	private $request;

	/**
	 * HTTP Headers map
	 * @var type
	 */
	protected $map = array(
		'method' => 'REQUEST_METHOD',
		'api_key' => 'HTTP_X_ELGG_APIKEY',
		'hmac' => 'HTTP_X_ELGG_HMAC',
		'hmac_algo' => 'HTTP_X_ELGG_HMAC_ALGO',
		'time' => 'HTTP_X_ELGG_TIME',
		'nonce' => 'HTTP_X_ELGG_NONCE',
		'posthash' => 'HTTP_X_ELGG_POSTHASH',
		'posthash_algo' => 'HTTP_X_ELGG_POSTHASH_ALGO',
		'content_type' => 'CONTENT_TYPE',
	);

	/**
	 * Constructor
	 * 
	 * @param HttpRequest $request Http request
	 */
	public function __construct(HttpRequest $request) {
		$this->request = $request;
	}

	/**
	 * Returns HTTP headers
	 * @return stdClass
	 */
	public function getHeaders() {
		$server = $this->request->server;
		$headers = new stdClass();
		foreach ($this->map as $key => $header) {
			$headers->$key = $server->get($header);
		}
		return $headers;
	}

	/**
	 * This function extracts the various header variables needed for the HMAC PAM
	 *
	 * @param stdClass $headers Headers to validate
	 * @return bool
	 * @throws GraphException Detailing any error
	 */
	function validateHeaders(stdClass $headers) {

		if ($headers->api_key == "") {
			throw new SecurityException(elgg_echo('Exception:MissingAPIKey'));
		}

		if ($headers->hmac == "") {
			throw new SecurityException(elgg_echo('Exception:MissingHmac'));
		}

		if ($headers->hmac_algo == "") {
			throw new SecurityException(elgg_echo('Exception:MissingHmacAlgo'));
		}

		if ($headers->time == "") {
			throw new SecurityException(elgg_echo('Exception:MissingTime'));
		}

		// Must have been sent within 25 hour period.
		// 25 hours is more than enough to handle server clock drift.
		// This values determines how long the HMAC cache needs to store previous
		// signatures. Heavy use of HMAC is better handled with a shorter sig lifetime.
		// See hypeGraph_cache_hmac_check_replay()
		if (($headers->time < (time() - 90000)) || ($headers->time > (time() + 90000))) {
			throw new SecurityException(elgg_echo('Exception:TemporalDrift'));
		}

		if ($headers->nonce == "") {
			throw new SecurityException(elgg_echo('Exception:MissingNonce'));
		}

		if ($headers->method != HttpRequest::METHOD_GET) {
			if ($headers->posthash == "") {
				throw new SecurityException(elgg_echo('Exception:MissingPOSTHash'));
			}

			if ($headers->posthash_algo == "") {
				throw new SecurityException(elgg_echo('Exception:MissingPOSTAlgo'));
			}

			if ($headers->content_type == "") {
				throw new SecurityException(elgg_echo('Exception:MissingContentType'));
			}
		}

		return $headers;
	}

	/**
	 * Map various algorithms to their PHP equivs.
	 * This also gives us an easy way to disable algorithms.
	 *
	 * @param string $algo The algorithm
	 *
	 * @return string The php algorithm
	 * @throws GraphException if an algorithm is not supported.
	 * @access private
	 */
	function mapApiHash($algo) {
		$algo = strtolower(sanitise_string($algo));
		$supported_algos = array(
			"md5" => "md5", // @todo Consider phasing this out
			"sha" => "sha1", // alias for sha1
			"sha1" => "sha1",
			"sha256" => "sha256"
		);

		if (!empty($supported_algos[$algo])) {
			return $supported_algos[$algo];
		}

		throw new SecurityException(elgg_echo('Exception:AlgorithmNotSupported', array($algo)));
	}

	/**
	 * Calculate the HMAC for the http request.
	 * This function signs an api request using the information provided. The signature returned
	 * has been base64 encoded and then url encoded.
	 *
	 * @param string $algo          The HMAC algorithm used
	 * @param string $time          String representation of unix time
	 * @param string $nonce         Nonce
	 * @param string $api_key       Your api key
	 * @param string $secret_key    Your private key
	 * @param string $get_variables URLEncoded string representation of the get variable parameters,
	 *                              eg "method=user&guid=2"
	 * @param string $post_hash     Optional sha1 hash of the post data.
	 *
	 * @return string The HMAC signature
	 */
	public function calculateHmac($algo, $time, $nonce, $api_key, $secret_key, $get_variables, $post_hash = "") {
		elgg_log("HMAC Parts: $algo, $time, $api_key, $secret_key, $get_variables, $post_hash");

		$ctx = hash_init($this->mapApiHash($algo), HASH_HMAC, $secret_key);

		hash_update($ctx, trim($time));
		hash_update($ctx, trim($nonce));
		hash_update($ctx, trim($api_key));
		hash_update($ctx, trim($get_variables));
		if (trim($post_hash) != "") {
			hash_update($ctx, trim($post_hash));
		}

		return urlencode(base64_encode(hash_final($ctx, true)));
	}

	/**
	 * Calculate a hash for some post data.
	 *
	 * @todo Work out how to handle really large bits of data.
	 *
	 * @param string $postdata The post data.
	 * @param string $algo     The algorithm used.
	 *
	 * @return string The hash.
	 * @access private
	 */
	function calculatePostHash($postdata, $algo) {
		$ctx = hash_init($this->mapApiHash($algo));
		hash_update($ctx, $postdata);
		return hash_final($ctx);
	}

	/**
	 * This function will do two things. Firstly it verifies that a HMAC signature
	 * hasn't been seen before, and secondly it will add the given hmac to the cache.
	 *
	 * @param string $hmac The hmac string.
	 *
	 * @return bool True if replay detected, false if not.
	 * @access private
	 */
	function cacheHmacCheckReplay($hmac) {
		// cache lifetime is 25 hours (this should be related to the time drift
		// allowed in get_and_validate_headers
		$cache = new HmacCache(90000);

		if (!$cache->load($hmac)) {
			$cache->save($hmac, $hmac);
			return false;
		}
		return true;
	}

}
