<?php

return [
	'routes' => [
		['name' => 'Remote#appService', 'url' => '/', 'verb' => 'GET'],
		['name' => 'Remote#test', 'url' => '/test', 'verb' => 'GET'],

		['name' => 'Remote#listRestoringPoint', 'url' => '/rp', 'verb' => 'GET'],
		['name' => 'Remote#detailsRestoringPoint', 'url' => '/rp/{restoringId}', 'verb' => 'GET'],
		['name' => 'Remote#partRestoringPoint', 'url' => '/rp/{restoringId}/part', 'verb' => 'GET'],
		['name' => 'Remote#downloadRestoringPoint', 'url' => '/rp/{restoringId}/download', 'verb' => 'GET'],
		['name' => 'Remote#createRestoringPoint', 'url' => '/rp', 'verb' => 'PUT'],
		['name' => 'Remote#updateRestoringPoint', 'url' => '/rp/{restoringId}', 'verb' => 'PUT'],
		['name' => 'Remote#uploadRestoringPoint', 'url' => '/rp/{restoringId}', 'verb' => 'POST']
	]
];
