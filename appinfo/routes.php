<?php

return [

	'ocs' => [
		['name' => 'Local#setupExport', 'url' => '/setup/{encrypted}', 'verb' => 'GET'],
		['name' => 'Local#getSettings', 'url' => '/settings', 'verb' => 'GET'],
		['name' => 'Local#setSettings', 'url' => '/settings', 'verb' => 'PUT'],
		['name' => 'Local#getRestoringPoints', 'url' => '/rp', 'verb' => 'GET'],
		['name' => 'Local#getExternalFolder', 'url' => '/external', 'verb' => 'GET'],
		['name' => 'Local#setExternalFolder', 'url' => '/external', 'verb' => 'POST'],
		['name' => 'Local#getAppData', 'url' => '/appdata', 'verb' => 'GET'],
		['name' => 'Local#setAppData', 'url' => '/appdata', 'verb' => 'POST'],

		['name' => 'Local#unsetExternalFolder', 'url' => '/external/{storageId}', 'verb' => 'DELETE'],
		['name' => 'Local#initAction', 'url' => '/action/{type}/{param}', 'verb' => 'POST']
	],

	'routes' => [
		['name' => 'Remote#appService', 'url' => '/', 'verb' => 'GET'],
		['name' => 'Remote#test', 'url' => '/test', 'verb' => 'GET'],

		['name' => 'Remote#listRestoringPoint', 'url' => '/rp', 'verb' => 'GET'],
		['name' => 'Remote#getRestoringPoint', 'url' => '/rp/{pointId}', 'verb' => 'GET'],
		['name' => 'Remote#healthRestoringPoint', 'url' => '/rp/{pointId}/health', 'verb' => 'GET'],
		[
			'name' => 'Remote#downloadRestoringPoint', 'url' => '/rp/{pointId}/{chunkName}download',
			'verb' => 'GET'
		],
		['name' => 'Remote#createRestoringPoint', 'url' => '/rp', 'verb' => 'PUT'],
		['name' => 'Remote#updateRestoringPoint', 'url' => '/rp/{pointId}', 'verb' => 'POST'],
		['name' => 'Remote#deleteRestoringPoint', 'url' => '/rp/{pointId}', 'verb' => 'DELETE'],
		['name' => 'Remote#uploadRestoringChunk', 'url' => '/rp/{pointId}/{chunkName}', 'verb' => 'POST']
	]
];
