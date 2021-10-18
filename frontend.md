### TODO:

- timestamp sur le dernier check d'un backup (health)
- mettre a jour le mockup_date si changement de configuration (time_slots, allow_weekday, ...)
- finaliser format des data sur les restoring points disponibles.


### Settings

```
$ curl -u 'admin:admin' -H "OCS-ApiRequest: true" -X GET "http://backup.local/ocs/v2.php/apps/backup/settings?format=json"|jq
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "date_full_rp": 1634409225,
      "date_partial_rp": 0,
      "time_slots": "23-5",
      "delay_full_rp": 14,
      "delay_partial_rp": 3,
      "allow_weekday": false,
      "pack_backup": true,
      "pack_compress": true,
      "pack_encrypt": true,
      "partial": 1634685473,
      "full": 1635635873
    }
  }
}
```

### External Folder

get the list of available/configured external filesystem:

```
$ curl -u 'admin:admin' -H "OCS-ApiRequest: true" -X GET "http://backup.local/ocs/v2.php/apps/backup/external?format=json" | jq
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": [
      {
        "storageId": 6,
        "storage": "sftp::test@127.0.0.1//home/test/",
        "root": "test_003/uploads"
      },
      {
        "storageId": 7,
        "storage": "sftp::test@127.0.0.1//home/test/back/",
        "root": ""
      }
    ]
  }
}
```

The external filesystem are created using the app files_external, once created there, they should be returned when running the request.
if root is empty, means that the external filesystem is not configured in backup app. To edit, create a request with the numeric storageId and the path to store to backup on the external filesystem.
At minimum, a trailing slah will be necessary; default should be something like `/backup/points/`

```
curl -u 'admin:admin' -H "OCS-ApiRequest: true" -X POST "http://backup.local/ocs/v2.php/apps/backup/external?format=json" -F 'storageId=7' -F 'root=/backups/points/oui' |jq
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "storageId": 7,
      "storage": "sftp::test@127.0.0.1//home/test/back/",
      "root": "/backups/points/oui"
    }
  }
}
```



### Actions

To initiate a complete backup (next tick of crontab).

- display a popup preventing that the instance will be in maintenance mode during the duration of the
  backup
- if `date_full_rp > 0 `: display a checkbox in the popup to generate a partial backup (instead of
  complete)

```
$ curl -u 'admin:admin' -H "OCS-ApiRequest: true" -X POST "http://backup.local/ocs/v2.php/apps/backup/action/backup/complete?format=json"
```

