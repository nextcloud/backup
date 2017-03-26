# Backup
A backup app that enables admins to create backups of a Nextcloud instance and also restore it


## OCC call
sudo -u www-data ./occ backup:create  /home/frank/test-backup  --password 12345
sudo -u www-data ./occ backup:restore  /home/frank/test-backup  --password 12345


## OCS call
curl -H "OCS-APIREQUEST: true" -X POST   http://frank:OJYTQ-HJTIJ-LPLDY-ROWFK@dev/nextcloud-dev/server/ocs/v2.php/apps/backup/api/v1/create     -d "path=/home/frank/test-backup" -d "password=12345"
curl -H "OCS-APIREQUEST: true" -X POST   http://frank:OJYTQ-HJTIJ-LPLDY-ROWFK@dev/nextcloud-dev/server/ocs/v2.php/apps/backup/api/v1/restore     -d "path=/home/frank/test-backup" -d "password=12345"
