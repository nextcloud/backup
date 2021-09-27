# Backup

This App allows admin to create backup images of their Nextcloud



## Restoring Points

A restoring point is an image of your Nextcloud at a specific time.
A restoring point can be:
- a '**full**' backup that contains all data that are managed by the App,
- an '**incremental**' backup that only contains modified files since your last 'full' backup.

### What data are available in a Restoring Point

Please note that the Backup App will not store ALL data from your Nextcloud. As an example, remote files does not have backup.
This is a quick list of what can be restored and what cannot be restored when using the Backup App:

A restoring point will store
- your current Nextcloud,
- the `apps/` folder,
- your local data, defined by `'datadirectory'` in `config/config.php`,
- the custom_apps folder (confirmation ?),
- the configuration in `config/config.php`,
- A full dump of your database.

A restoring point will **NOT** store:
- Remote data, even if the filesystem is available locally.

A Restoring Point also contains a file named `metadata.json` that contains information like:
- Version of your Nextcloud
- The ID of the full backup in case of incremental backups,
- The list of compressed file that contains the backup files,
- Checksum for those files,
- the date of the restoring point.

## Quick documentation:

### Manage remote instance to store your backups remotely

You can upload your backup files on a remote instance

**Add a remote instance to store your backups**

    ./occ backup:remote:add cloud.example.net

**List configured remote instance**

    ./occ backup:remote:list

**Remove remote instance from the list**

    ./occ backup:remote:remove cloud.example.net


### Manage your restoring point


**Create a new Restoring Point**

    ./occ backup:point:create [--incremental]

The `--incremental` option will create an incremental backup


**Upload a Restoring Point**

    ./occ backup:point:upload <pointId>

This will request all configured remote instances to check the sanity of any previous upload for this Restoring 
Point, and will only upload missing/faulty file.


**List restoring points**

    ./occ backup:point:list

You can search and compare restoring point available locally and on configured remote instance.


**Search for a specific file:**

    ./occ backup:node:search [--since|--until|--point] <string>

Search for a file, based on its name.

example: `./occ backup:node:search test.jpg --since 2021-09-23`


**History of specific a file:**

    ./occ backup:node:history [--since|--until] <dataPack> <fullPath>

Display the history of a file.

example: `./occ backup:node:history data cult/files/backup1.md`


**Import a Restoring Point**

If you start using the app, you will face at one point a situation where an important Restoring Point is available somewhere but cannot be find in your database.
As an example, when restoring a Backup, all Restoring Point created after this backup won't be in database anymore. This is normal as restoring the backup fully overwrite your database.
In that case, you can run this command:

    ./occ backup:point:scan <pointId>

If it cannot be found, you will need to manually copy the folder that contains the Restoring Point in the appdata folder: data/appdata_qwerty123/backup/


**Verify integrity of a Restoring Point**

    ./occ backup:point:details <pointId>



## Restoration




## Exporting configuration

This is an important step of your configuration of the Backup App
Some information will be needed in case you start storing your backup on remote instances:

- The identity of your Nextcloud,
- The encryption key used to encrypt your backup.

**While the identity can be changed and your access to the remote files can be restored by 
executing some command on the remote instance to update your new identity, a missing encryption 
key means that your remote backup cannot be decrypted and are totally useless.**

**Please note that creating a new identity will disable the sanity check on the metadata file.**

    ./occ backup:setup:export > ~/backup_setup.json

    ./occ backup:setup:import < ~/backup_setup.json



### Known issues, missing features:

- download does not work
- files are not encrypted when uploading to a remote instance
- cannot upload restoring point with file bigger than 100M
- uploading a parent RP after a dependant incremental backup does not remove the 'orphan' tag
- Importing a Restoring Point using `backup:point:scan` from an external folder
- Add remote instance to `backup:point:details`
- encrypting exported setup with a passphrase
