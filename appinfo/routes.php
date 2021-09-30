<?php

return [
	'routes' => [
		['name' => 'Remote#appService', 'url' => '/', 'verb' => 'GET'],
		['name' => 'Remote#test', 'url' => '/test', 'verb' => 'GET'],

		['name' => 'Remote#listRestoringPoint', 'url' => '/rp', 'verb' => 'GET'],
		['name' => 'Remote#getRestoringPoint', 'url' => '/rp/{pointId}', 'verb' => 'GET'],
		['name' => 'Remote#healthRestoringPoint', 'url' => '/rp/{pointId}/health', 'verb' => 'GET'],
		['name' => 'Remote#downloadRestoringPoint', 'url' => '/rp/{pointId}/download', 'verb' => 'GET'],
		['name' => 'Remote#createRestoringPoint', 'url' => '/rp', 'verb' => 'PUT'],
		['name' => 'Remote#updateRestoringPoint', 'url' => '/rp/{pointId}', 'verb' => 'PUT'],
//		['name' => 'Remote#uploadRestoringChunk', 'url' => '/rp/{pointId}', 'verb' => 'POST']
		['name' => 'Remote#uploadRestoringChunk', 'url' => '/rp/{pointId}/{chunkId}', 'verb' => 'POST']
	]
];
