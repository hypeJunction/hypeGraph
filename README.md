hypeGraph
=========

RESTful API Graph and Web Services for Elgg

## Features

* RESTful Secure Graph Access
* Admin interface for managing API consumers
* Granular access controls for API resource on per-consumer basis

## Requirements

* Requires PHP 5.5
* Requires hypeApps plugin

## Acknowledgements

* Some of the classes are based on the core web services plugin, but have been heavily
refactored to suit the need of this plugin. Nevertheless, thanks go to the original authors.

* The approach has been inspired by Evan Winslow, and his multiple posts on the subject.
I am not entirely sure if he likes the actual implementation, but some of his ideas have been
incorporated, so thanks.

* Thanks go to PlayAtMe, Bodyology School of Massage, and Ambercase, for partially
sponsoring various elements of the plugin.

## Authentication

You can configure which authentication methods are allowed on your site.
Examples below assume you have API key authentication enabled.

Note that calling REST/Graph endpoints will terminate current user session,
and use registered PAM handlers to reauthenticate. In order to test web services
through a browser, or to access Graph endpoints via JS, drop ```/services/api```
from your Endpoint URLs.

### API Authentication

#### API Key

You can authenticate your API requests with an API key. Make sure to have HTTPs enabled!

#### HMAC Signature

API requests can also be signed with HMAC using API secret. See the code for implementation details.


### User Authentication

#### Username and password

Though highly discouraged, you can add username and password to each request to authenticate the user.
This is handled by the core userpass PAM.

#### Access Token

You can acquire a user token at the ```POST /me/token``` endpoint. You will need to provider username and password,
and will receive a token in response. The tokens are valid for 60 minutes.
You can use ```POST `/:user/token``` endpoint to acquire a token to perform actions on behalf of a user you can manage (```canEdit()``` required).
So, as an authenticated admin, you can get an access token for any user in the system, and perform actions on their behalf.

You can exchange a user token for long-living one (30 days), using ```PUT /:user/token``` endpoint.

#### API Consumer username and password

You can set up your API Consumer with usernames and passwords, which they can use to authenticate as the user, who owns the API Consumer entity.
This is helpful in cases, where you don't want to pass around actual user credentials (and inconveniencing users with frequent password resets).

## Endpoints

To access the graph you can use one of the following endpoints:

Using an API client:
*```https://{{site-url}}/services/api/graph/{{node}}/{{edge}}?api_key={{api-key}}```*,

Using Elgg action tokens (e.g. with ```elgg.action()``` JS method):
*```https://{{site-url}}/graph/{{node}}/{{edge}}```*,

where
 - ```{{site-url}}``` is your Elgg site URL
 - ```{{node}}``` is an ID of a node, e.g. uid of a resource
 - ```{{edge}}``` is the edge of a node, e.g. friends
 - ```{{api-key}}``` is the API key assigned to your client

Unlike RPC services, you can not pass ```{{format}}```.
Use HTTP Accept header to specify the Content-Type you would like to consume, e.g.

```Accept: application/json```

## Nodes and Edges

Routes are represented with nodes and edges, where as a node is an Elgg entity,
annotation, or river item, and an edge is an alias for a subset of resources.

For example, ```POST /me/activity``` route allows the consumer to create a new
river time for a currently authenticated user, while ```GET /ge25/members```
retrieves a list of members in a group.

Note that the graph does not encourage the use of ```guid```s, instead it uses ```uid```s,
which are strings comprising of a prefix and a numeric id. UIDs are mapped as follows:

* ```ElggUser``` node uid is prefixed with ```ue```, e.g. ```ue2521```
* ```ElggObject``` node uid is prefixed with ```oe```, e.g. ```oe2758```
* ```ElggGroup``` node uid is prefixed with ```ge```, e.g. ```ge2737```
* ```ElggSite``` node uid is prefixed with ```se```, e.g. ```se1```
* ```ElggRiverItem``` node uid is prefixed with ```rv```, e.g. ```rv3738```
* ```ElggAnnotation``` node uid is prefixed with ```an```, e.g. ```an7789```
* ```ElggMetadata``` node uid is prefixed with ```md```, e.g. ```md74839```
* ```ElggRelationship``` node uid is prefixed with ```rl```, e.g. ```rl4657```

Integer UIDs fallback to entity guids. Usernames can also be used as UIDs, e.g.
for a logged in user with username ```UserWithUsername``` and guid ```2521```
the following are identical calls:

* ```/me/friends```
* ```/ue2521/friends```
* ```/2521/friends```
* ```/UserWithUsername/friends```

You can map your routes to ```\hypeJunction\Graph\ControllerInterface``` controllers.

To see a full list of routes, use ```GET /``` graph endpoint.

Entity types need to be exposed explicitly by providing an alias.

```php

// Closures are used for illustration. Don't do that in your plugins, use a defined callable

elgg_register_plugin_hook_handler('aliases', 'graph', function($hook, $type, $return) {
	$return['object']['my_cool_subtype'] = ':my_cool_subtype';
	return $return;
});

elgg_register_plugin_hook_handler('routes', 'graph', function($hook, $type, $return) {
	$return[':my_cool_subtype'] = MyCoolSubtypeController::class;
	$return[':user/coolness'] = UserCoolnessController::class;
});
```

Your controllers should the define the parameters expected and the business logic
for each HTTP request method.


## Response

### Format

Responses will be sent with appropriate HTTP headers and will be in the following format:

```json
{
	'status': 200,
	'message': 'OK',
	'result' : {
		'key' => 'value'
	}
}
```

```json
{
	'status': 404,
	'message': 'Requested resources was not found',
	'result' : [],
	'log': []
}
```

To receive information about runtime errors, you can add ``?debug=true``` to your request.
This will add a ```log``` array to the response.

For initial testing and debugging, you can enable debug mode in plugin settings.
This will add additional data to the response, such as request and query parameters,
currently logged in user and API consumer, exception trace etc.


### Resource export fields

You have full control over what fields are exported for any given resource type (in any particular consumer context or user context).

* ```'fields','graph'``` plugin hook handler can be used to define allowed fields
This hook receives ```'node'``` parameter, which can be either an entity or annotation or metadata

* ```'to:object',$type``` plugin hook can be used to filter the output of a given resource
This is a core hook, triggered by ```ElggEntity::toObject()```, so make sure you sniff the context before overriding the output

* ```'result','graph'``` plugin hook can be used to filter the final result before it is sent to the requestor
This hook received ```GenericResult``` instance as the return value.

## Magic


### Magic nodes

* ```/me``` nodes resolve to ```/:user``` nodes with the currently authenticated user uid
* ```/site``` nodes resolve to ```/:site``` nodes for the current site


### Magic query parameters

* ```?debug=true``` will append a log of runtime errors to the response
* ```?fields=uid,name,owner``` will constrain resource export to only those fields. If fields are not defined as allowed for that resource type, they will return ```null```


## Batch Results

### Sorting

Batch results can be sorted by a number of fields. You can pass ```sort``` parameter with your query, that can take in multiple field => direction definitions
Results can be sorted by ```alpha``` (alphabetically according to name/title) or by any attribute field from the entities table (e.g. ```uid```, ```subtype``` etc)

```php

$test = new Client($api_key);
$url = $test->buildGraphUrl('/site/groups');
$result = $test->get($url, array(
	'sort' => array(
		'alpha' => 'ASC',
		'time_created' => 'DESC',
	),
	'fields' => 'uid,name',
	'limit' => 5,
	'offset' => 15,
));

```

### Response Format

Request above will output something similar to this:

```json
{
  "status": 200,
  "message": "OK"
  "result": {
    "total": 69,
    "limit": 5,
    "offset": 15,
    "nodes": [
      {
        "uid": "ge7210",
        "name": "Enim voluptatem nisi veniam deserunt et."
      },
      {
        "uid": "ge7199",
        "name": "Eos qui minima aperiam."
      },
      {
        "uid": "ge7202",
        "name": "Eos quidem dolorem cum voluptas quis quo."
      },
      {
        "uid": "ge7208",
        "name": "Et qui iste quo molestiae sit veritatis."
      },
      {
        "uid": "ge7204",
        "name": "Explicabo ullam saepe debitis qui."
      }
    ]
  }
}
```

## List of endpoints

<table>
			<tbody>
				<tr><th>Endpoint</th><th>Description</th></tr>
				<tr>
					<td><strong> GET / </strong></td>
					<td>
						<p>Returns a list of graph endpoints configured on the site</p>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:site </strong></td>
					<td>
						<p>Returns site entity attritubes and basic configuration values</p>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:site/users </strong></td>
					<td>
						<p>Returns a list of users registered on the site</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>sort</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>Array</td>
									<td>&nbsp;</td>
									<td>Sorting order</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> POST /:site/users </strong></td>
					<td>
						<p>Registers a new user on the site</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>email</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Email address</td>
								</tr>
								<tr>
									<td>username</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Email address (generated automatically, if omitted)</td>
								</tr>
								<tr>
									<td>password</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>graph:param:password</td>
								</tr>
								<tr>
									<td>name</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Display name</td>
								</tr>
								<tr>
									<td>language</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>en</td>
									<td>&nbsp;</td>
									<td>Language code</td>
								</tr>
								<tr>
									<td>notify</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>1</td>
									<td>&nbsp;</td>
									<td>Send email notification</td>
								</tr>
								<tr>
									<td>friend_uid</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>UID of another user that invited this user</td>
								</tr>
								<tr>
									<td>invitecode</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Invitation code</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:site/activity </strong></td>
					<td>
						<p>Returns a list of latest site activity (river)</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>types</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Entity types</td>
								</tr>
								<tr>
									<td>subtypes</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Entity subtypes</td>
								</tr>
								<tr>
									<td>action_types</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Action types</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> POST /:site/activity </strong></td>
					<td>
						<p>Adds a new activity (river) item to the feed</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>action</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Action</td>
								</tr>
								<tr>
									<td>view</td>
									<td>string</td>
									<td>X</td>
									<td>river/elements/layout</td>
									<td>&nbsp;</td>
									<td>Existing view</td>
								</tr>
								<tr>
									<td>subject_uid</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>UID of the subject</td>
								</tr>
								<tr>
									<td>object_uid</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>UID of the object</td>
								</tr>
								<tr>
									<td>target_uid</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>UID of the target</td>
								</tr>
								<tr>
									<td>annotation_uid</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>UID of the annotation</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:user </strong></td>
					<td>
						<p>Returns a user</p>
					</td>
				</tr>
				<tr>
					<td><strong> DELETE /:user </strong></td>
					<td>
						<p>Deletes a user</p>
					</td>
				</tr>
				<tr>
					<td><strong> POST /:user/token </strong></td>
					<td>
						<p>Create a new access token for the user</p>
					</td>
				</tr>
				<tr>
					<td><strong> PUT /:user/token </strong></td>
					<td>
						<p>Exchanges a short lived access token for a token with a 30-day validity</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>auth_token</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Access Token</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> DELETE /:user/token </strong></td>
					<td>
						<p>Revokes a user token</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>auth_token</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Access Token</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:object </strong></td>
					<td>
						<p>Returns an object</p>
					</td>
				</tr>
				<tr>
					<td><strong> DELETE /:object </strong></td>
					<td>
						<p>Deletes an object</p>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:activity </strong></td>
					<td>
						<p>Returns a single activity (river) item</p>
					</td>
				</tr>
				<tr>
					<td><strong> DELETE /:activity </strong></td>
					<td>
						<p>Deletes a single activity (river) item</p>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:user/activity </strong></td>
					<td>
						<p>Returns a list of activity (river) items where a user is either a subject, object or target</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>types</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Entity types</td>
								</tr>
								<tr>
									<td>subtypes</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Entity subtypes</td>
								</tr>
								<tr>
									<td>action_types</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Action types</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> POST /:user/activity </strong></td>
					<td>
						<p>Adds a new activity (river) item with the user as a subject</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>action</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Action</td>
								</tr>
								<tr>
									<td>view</td>
									<td>string</td>
									<td>X</td>
									<td>river/elements/layout</td>
									<td>&nbsp;</td>
									<td>Existing view</td>
								</tr>
								<tr>
									<td>object_uid</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>UID of the object</td>
								</tr>
								<tr>
									<td>target_uid</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>UID of the target</td>
								</tr>
								<tr>
									<td>annotation_uid</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>UID of the annotation</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:comment </strong></td>
					<td>
						<p>Returns a single comment</p>
					</td>
				</tr>
				<tr>
					<td><strong> PUT /:comment </strong></td>
					<td>
						<p>Update s a single comment</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>comment</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Comment</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> DELETE /:comment </strong></td>
					<td>
						<p>Deletes a single comment</p>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:object/comments </strong></td>
					<td>
						<p>Returns a list of comments made on the object</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>sort</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>Array</td>
									<td>&nbsp;</td>
									<td>Sorting order</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> POST /:object/comments </strong></td>
					<td>
						<p>Adds a new comment on the object</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>comment</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Comment</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:like </strong></td>
					<td>
						<p>Returns a single like</p>
					</td>
				</tr>
				<tr>
					<td><strong> DELETE /:like </strong></td>
					<td>
						<p>Deletes a single like</p>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:object/likes </strong></td>
					<td>
						<p>Returns a list of object likes</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> POST /:object/likes </strong></td>
					<td>
						<p>Likes an object on behalf of the authenticated user</p>
					</td>
				</tr>
				<tr>
					<td><strong> DELETE /:object/likes </strong></td>
					<td>
						<p>Unlikes an object on behalf of the authenticated user</p>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:group </strong></td>
					<td>
						<p>Returns a single group</p>
					</td>
				</tr>
				<tr>
					<td><strong> PUT /:group </strong></td>
					<td>
						<p>Updates a group</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>name</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Group name</td>
								</tr>
								<tr>
									<td>membership</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>0<br />2</td>
									<td>Group membership permissions</td>
								</tr>
								<tr>
									<td>vis</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>0<br />2<br />1</td>
									<td>Who can see this group?</td>
								</tr>
								<tr>
									<td>content_access_mode</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>unrestricted<br />members_only</td>
									<td>Accessibility of group content</td>
								</tr>
								<tr>
									<td>description</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Description</td>
								</tr>
								<tr>
									<td>briefdescription</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Brief description</td>
								</tr>
								<tr>
									<td>interests</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Tags</td>
								</tr>
								<tr>
									<td>blog_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>yes<br />no</td>
									<td>Enable group blog</td>
								</tr>
								<tr>
									<td>bookmarks_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>yes<br />no</td>
									<td>Enable group bookmarks</td>
								</tr>
								<tr>
									<td>file_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>yes<br />no</td>
									<td>Enable group files</td>
								</tr>
								<tr>
									<td>activity_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>yes<br />no</td>
									<td>Enable group activity</td>
								</tr>
								<tr>
									<td>forum_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>yes<br />no</td>
									<td>Enable group discussion</td>
								</tr>
								<tr>
									<td>pages_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>yes<br />no</td>
									<td>Enable group pages</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> DELETE /:group </strong></td>
					<td>
						<p>Deletes a group</p>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:group/members </strong></td>
					<td>
						<p>Returns a list of group members</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>sort</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>Array</td>
									<td>&nbsp;</td>
									<td>Sorting order</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> POST /:group/members </strong></td>
					<td>
						<p>Adds user as a member of a group (or creates a membership request for closed group)</p>
					</td>
				</tr>
				<tr>
					<td><strong> DELETE /:group/members </strong></td>
					<td>
						<p>Revokes membership in a group (or deletes membership request or revokes invitation)</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>relationship</td>
									<td>enum</td>
									<td>X</td>
									<td>member</td>
									<td>member<br />membership_request<br />invited</td>
									<td>Relationship name</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:site/groups </strong></td>
					<td>
						<p>Returns a list of all groups on the site</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>sort</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>Array</td>
									<td>&nbsp;</td>
									<td>Sorting order</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> POST /:site/groups </strong></td>
					<td>
						<p>Creates a new group</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>name</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Group name</td>
								</tr>
								<tr>
									<td>membership</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>0<br />2</td>
									<td>Group membership permissions</td>
								</tr>
								<tr>
									<td>vis</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>0<br />2<br />1</td>
									<td>Who can see this group?</td>
								</tr>
								<tr>
									<td>content_access_mode</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>members_only</td>
									<td>unrestricted<br />members_only</td>
									<td>Accessibility of group content</td>
								</tr>
								<tr>
									<td>description</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Description</td>
								</tr>
								<tr>
									<td>briefdescription</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Brief description</td>
								</tr>
								<tr>
									<td>interests</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Tags</td>
								</tr>
								<tr>
									<td>blog_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group blog</td>
								</tr>
								<tr>
									<td>bookmarks_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group bookmarks</td>
								</tr>
								<tr>
									<td>file_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group files</td>
								</tr>
								<tr>
									<td>activity_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group activity</td>
								</tr>
								<tr>
									<td>forum_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group discussion</td>
								</tr>
								<tr>
									<td>pages_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group pages</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:user/groups </strong></td>
					<td>
						<p>Returns a list of all groups created by the user</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>sort</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>Array</td>
									<td>&nbsp;</td>
									<td>Sorting order</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> POST /:user/groups </strong></td>
					<td>
						<p>Creates a new group</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>name</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Group name</td>
								</tr>
								<tr>
									<td>membership</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>0<br />2</td>
									<td>Group membership permissions</td>
								</tr>
								<tr>
									<td>vis</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>0<br />2<br />1</td>
									<td>Who can see this group?</td>
								</tr>
								<tr>
									<td>content_access_mode</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>members_only</td>
									<td>unrestricted<br />members_only</td>
									<td>Accessibility of group content</td>
								</tr>
								<tr>
									<td>description</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Description</td>
								</tr>
								<tr>
									<td>briefdescription</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Brief description</td>
								</tr>
								<tr>
									<td>interests</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Tags</td>
								</tr>
								<tr>
									<td>blog_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group blog</td>
								</tr>
								<tr>
									<td>bookmarks_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group bookmarks</td>
								</tr>
								<tr>
									<td>file_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group files</td>
								</tr>
								<tr>
									<td>activity_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group activity</td>
								</tr>
								<tr>
									<td>forum_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group discussion</td>
								</tr>
								<tr>
									<td>pages_enable</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>yes</td>
									<td>yes<br />no</td>
									<td>Enable group pages</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:user/groups/membership </strong></td>
					<td>
						<p>Returns a list of all groups a user is a member of</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>sort</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>Array</td>
									<td>&nbsp;</td>
									<td>Sorting order</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:blog </strong></td>
					<td>
						<p>Returns a single blog</p>
					</td>
				</tr>
				<tr>
					<td><strong> PUT /:blog </strong></td>
					<td>
						<p>Updates a blog post</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>title</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Title</td>
								</tr>
								<tr>
									<td>description</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Body</td>
								</tr>
								<tr>
									<td>excerpt</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Excerpt</td>
								</tr>
								<tr>
									<td>status</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>draft<br />published</td>
									<td>Status</td>
								</tr>
								<tr>
									<td>comments_on</td>
									<td>enum</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>On<br />Off</td>
									<td>Comments</td>
								</tr>
								<tr>
									<td>tags</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Tags</td>
								</tr>
								<tr>
									<td>access_id</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>ID of an access collection</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> DELETE /:blog </strong></td>
					<td>
						<p>Deletes a blog post</p>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:group/blogs </strong></td>
					<td>
						<p>Returns a list of all group blogs</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>sort</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>Array</td>
									<td>&nbsp;</td>
									<td>Sorting order</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> POST /:group/blogs </strong></td>
					<td>
						<p>Creates a new group blog</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>title</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Title</td>
								</tr>
								<tr>
									<td>description</td>
									<td>string</td>
									<td>X</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Body</td>
								</tr>
								<tr>
									<td>excerpt</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Excerpt</td>
								</tr>
								<tr>
									<td>status</td>
									<td>enum</td>
									<td>X</td>
									<td>published</td>
									<td>draft<br />published</td>
									<td>Status</td>
								</tr>
								<tr>
									<td>comments_on</td>
									<td>enum</td>
									<td>X</td>
									<td>On</td>
									<td>On<br />Off</td>
									<td>Comments</td>
								</tr>
								<tr>
									<td>tags</td>
									<td>string</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Tags</td>
								</tr>
								<tr>
									<td>access_id</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>ID of an access collection</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:site/blogs </strong></td>
					<td>
						<p>Returns a list of all blogs on the site</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>sort</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>Array</td>
									<td>&nbsp;</td>
									<td>Sorting order</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td><strong> GET /:user/blogs </strong></td>
					<td>
						<p>Returns a list of all blogs by the user</p>
						<table class="elgg-table mtl mbl">
							<thead>
								<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
							</thead>
							<tbody>
								<tr>
									<td>limit</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>10</td>
									<td>&nbsp;</td>
									<td>Number of entries to return (max 50)</td>
								</tr>
								<tr>
									<td>offset</td>
									<td>integer</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td>Offset from the start of the list</td>
								</tr>
								<tr>
									<td>sort</td>
									<td>array</td>
									<td>&nbsp;</td>
									<td>Array</td>
									<td>&nbsp;</td>
									<td>Sorting order</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
			<td><strong> POST /:user/blogs </strong></td>
			<td>
				<p>Creates a new blog</p>
				<table class="elgg-table mtl mbl">
					<thead>
						<tr><th>Parameter</th><th>Type</th><th>Required</th><th>Default</th><th>Enum</th><th>Description</th></tr>
					</thead>
					<tbody>
						<tr>
							<td>title</td>
							<td>string</td>
							<td>X</td>
							<td>&nbsp;</td>
							<td>&nbsp;</td>
							<td>Title</td>
						</tr>
						<tr>
							<td>description</td>
							<td>string</td>
							<td>X</td>
							<td>&nbsp;</td>
							<td>&nbsp;</td>
							<td>Body</td>
						</tr>
						<tr>
							<td>excerpt</td>
							<td>string</td>
							<td>&nbsp;</td>
							<td>&nbsp;</td>
							<td>&nbsp;</td>
							<td>Excerpt</td>
						</tr>
						<tr>
							<td>status</td>
							<td>enum</td>
							<td>X</td>
							<td>published</td>
							<td>draft<br />published</td>
							<td>Status</td>
						</tr>
						<tr>
							<td>comments_on</td>
							<td>enum</td>
							<td>X</td>
							<td>On</td>
							<td>On<br />Off</td>
							<td>Comments</td>
						</tr>
						<tr>
							<td>tags</td>
							<td>string</td>
							<td>&nbsp;</td>
							<td>&nbsp;</td>
							<td>&nbsp;</td>
							<td>Tags</td>
						</tr>
						<tr>
							<td>access_id</td>
							<td>integer</td>
							<td>&nbsp;</td>
							<td>&nbsp;</td>
							<td>&nbsp;</td>
							<td>ID of an access collection</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
	</tbody>
</table>