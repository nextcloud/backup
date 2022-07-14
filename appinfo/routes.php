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
	]
];
