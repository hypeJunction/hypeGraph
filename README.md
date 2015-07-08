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


## List of endpoints

Some of the parameters may vary depending on your site configuration.

### Added in 1.0

Endpoint

Description

**GET /**

Returns a list of graph endpoints configured on the site

**GET /:site**

Returns site entity attritubes and basic configuration values

**GET /:site/users**

Returns a list of users registered on the site

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|sort|array||Array||Sorting order|

**POST /:site/users**

Registers a new user on the site

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|email|string|X|||Email address|
|username|string||||Email address (generated automatically, if omitted)|
|password|string||||graph:param:password|
|name|string||||Display name|
|language|string||en||Language code|
|notify|string||1||Send email notification|
|friend\_uid|string||||UID of another user that invited this user|
|invitecode|string||||Invitation code|

**GET /:site/activity**

Returns a list of latest site activity (river)

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|types|array||||Entity types|
|subtypes|array||||Entity subtypes|
|action\_types|array||||Action types|

**POST /:site/activity**

Adds a new activity (river) item to the feed

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|action|string|X|||Action|
|view|string|X|river/elements/layout||Existing view|
|subject\_uid|string|X|||UID of the subject|
|object\_uid|string||||UID of the object|
|target\_uid|string||||UID of the target|
|annotation\_uid|string||||UID of the annotation|

**GET /:user**

Returns a user

**DELETE /:user**

Deletes a user

**POST /:user/token**

Create a new access token for the user

**PUT /:user/token**

Exchanges a short lived access token for a token with a 30-day validity

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|auth\_token|string|X|||Access Token|

**DELETE /:user/token**

Revokes a user token

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|auth\_token|string|X|||Access Token|

**GET /:object**

Returns an object

**DELETE /:object**

Deletes an object

**GET /:activity**

Returns a single activity (river) item

**DELETE /:activity**

Deletes a single activity (river) item

**GET /:user/activity**

Returns a list of activity (river) items where a user is either a subject, object or target

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|types|array||||Entity types|
|subtypes|array||||Entity subtypes|
|action\_types|array||||Action types|

**POST /:user/activity**

Adds a new activity (river) item with the user as a subject

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|action|string|X|||Action|
|view|string|X|river/elements/layout||Existing view|
|object\_uid|string||||UID of the object|
|target\_uid|string||||UID of the target|
|annotation\_uid|string||||UID of the annotation|

**GET /:comment**

Returns a single comment

**PUT /:comment**

Update s a single comment

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|comment|string|X|||Comment|

**DELETE /:comment**

Deletes a single comment

**GET /:object/comments**

Returns a list of comments made on the object

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|sort|array||Array||Sorting order|

**POST /:object/comments**

Adds a new comment on the object

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|comment|string|X|||Comment|

**GET /:like**

Returns a single like

**DELETE /:like**

Deletes a single like

**GET /:object/likes**

Returns a list of object likes

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|

**POST /:object/likes**

Likes an object on behalf of the authenticated user

**DELETE /:object/likes**

Unlikes an object on behalf of the authenticated user

**GET /:group**

Returns a single group

**PUT /:group**

Updates a group

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|name|string||||Group name|
|membership|enum|||0
2|Group membership permissions|
|vis|enum|||0
2
1|Who can see this group?|
|content\_access\_mode|enum|||unrestricted
members\_only|Accessibility of group content|
|description|string||||Description|
|briefdescription|string||||Brief description|
|interests|string||||Tags|
|blog\_enable|string|||yes
no|Enable group blog|
|bookmarks\_enable|string|||yes
no|Enable group bookmarks|
|file\_enable|string|||yes
no|Enable group files|
|activity\_enable|string|||yes
no|Enable group activity|
|forum\_enable|string|||yes
no|Enable group discussion|
|pages\_enable|string|||yes
no|Enable group pages|

**DELETE /:group**

Deletes a group

**GET /:group/members**

Returns a list of group members

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|sort|array||Array||Sorting order|

**POST /:group/members**

Adds user as a member of a group (or creates a membership request for closed group)

**DELETE /:group/members**

Revokes membership in a group (or deletes membership request or revokes invitation)

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|relationship|enum|X|member|member
membership\_request
invited|Relationship name|

**GET /:site/groups**

Returns a list of all groups on the site

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|sort|array||Array||Sorting order|

**POST /:site/groups**

Creates a new group

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|name|string|X|||Group name|
|membership|enum|||0
2|Group membership permissions|
|vis|enum|||0
2
1|Who can see this group?|
|content\_access\_mode|enum||members\_only|unrestricted
members\_only|Accessibility of group content|
|description|string||||Description|
|briefdescription|string||||Brief description|
|interests|string||||Tags|
|blog\_enable|string||yes|yes
no|Enable group blog|
|bookmarks\_enable|string||yes|yes
no|Enable group bookmarks|
|file\_enable|string||yes|yes
no|Enable group files|
|activity\_enable|string||yes|yes
no|Enable group activity|
|forum\_enable|string||yes|yes
no|Enable group discussion|
|pages\_enable|string||yes|yes
no|Enable group pages|

**GET /:user/groups**

Returns a list of all groups created by the user

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|sort|array||Array||Sorting order|

**POST /:user/groups**

Creates a new group

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|name|string|X|||Group name|
|membership|enum|||0
2|Group membership permissions|
|vis|enum|||0
2
1|Who can see this group?|
|content\_access\_mode|enum||members\_only|unrestricted
members\_only|Accessibility of group content|
|description|string||||Description|
|briefdescription|string||||Brief description|
|interests|string||||Tags|
|blog\_enable|string||yes|yes
no|Enable group blog|
|bookmarks\_enable|string||yes|yes
no|Enable group bookmarks|
|file\_enable|string||yes|yes
no|Enable group files|
|activity\_enable|string||yes|yes
no|Enable group activity|
|forum\_enable|string||yes|yes
no|Enable group discussion|
|pages\_enable|string||yes|yes
no|Enable group pages|

**GET /:user/groups/membership**

Returns a list of all groups a user is a member of

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|sort|array||Array||Sorting order|

**GET /:blog**

Returns a single blog

**PUT /:blog**

Updates a blog post

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|title|string||||Title|
|description|string||||Body|
|excerpt|string||||Excerpt|
|status|enum|||draft
published|Status|
|comments\_on|enum|||On
Off|Comments|
|tags|string||||Tags|
|access\_id|integer||||ID of an access collection|

**DELETE /:blog**

Deletes a blog post

**GET /:group/blogs**

Returns a list of all group blogs

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|sort|array||Array||Sorting order|

**POST /:group/blogs**

Creates a new group blog

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|title|string|X|||Title|
|description|string|X|||Body|
|excerpt|string||||Excerpt|
|status|enum|X|published|draft
published|Status|
|comments\_on|enum|X|On|On
Off|Comments|
|tags|string||||Tags|
|access\_id|integer||||ID of an access collection|

**GET /:site/blogs**

Returns a list of all blogs on the site

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|sort|array||Array||Sorting order|

**GET /:user/blogs**

Returns a list of all blogs by the user

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|limit|integer||10||Number of entries to return (max 50)|
|offset|integer||||Offset from the start of the list|
|sort|array||Array||Sorting order|

**POST /:user/blogs**

Creates a new blog

|Parameter|Type|Required|Default|Enum|Description|
|:--------|:---|:-------|:------|:---|:----------|
|title|string|X|||Title|
|description|string|X|||Body|
|excerpt|string||||Excerpt|
|status|enum|X|published|draft
published|Status|
|comments\_on|enum|X|On|On
Off|Comments|
|tags|string||||Tags|
|access\_id|integer||||ID of an access collection|


