# Backup

This App allows admin to create and store backup images of their Nextcloud


- [Restoring Points](#restoring-point)
- [How the Backup App manage your data](#backup-manage-data)
- [Upload to External Storages](#external-storages)
- [Important details about your data](#important)
- [Available `occ` commands](#occ)


<a name="restoring-point"></a>
## Restoring Points

A restoring point is an image of your Nextcloud at a specific time. A restoring point can be:

- '**Complete**' (or Full) and contains a backup of :
    * the instance of Nextcloud (core),
    * the apps of the Nextcloud (from `apps/` and `custom_apps/`),
    * A dump of the database,
    * the data of the Nextcloud including users' files.

- '**Partial**' (or Incremental) that contains a backup of :
    * the instance of Nextcloud,
    * the apps of the Nextcloud,
    * A dump of the database,
    * data that have been generated or edited since the last **Complete Backup**

### What data are available in a Restoring Point

Please note that the Backup App will not store ALL data from your Nextcloud. As an example, remote files
does not have backup.  
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

A Restoring Point also contains a file named `restoring-point.data` that contains metadata about the
backup:

- Version of your Nextcloud
- The ID of the parent backup in case of partial backup,
- The list of compressed file that contains the backup files,
- Checksum for those files,
- the date of the restoring point,
- information related to the health of the files

_Note: the file `restoring-point.data` can confirm the integrity of all files and parts of the backup. If
the file is lost, it is still possible to restore a restoring point.  
The normal process is to re-create the `restoring-point.data` a new one, however :

- there is no way to confirm the integrity of content of the backup,
- the restoring process will require some knowledge from the admin about the infrastructure from the
  original instance that generated the backup.

<a name="backup-manage-data"></a>
## How the Backup App manage your data

### The timing

The settings available in the Admin Settings/Backup page, allow an admin to configure when the next
backups will be run and at which rate:
 ...
### The first pass (the backup process)
 ...

### The second pass (the packing process)
 ...


<a name="external-storages"></a>
## Upload to External Storages


<a name="important"></a>
## Important details about your data

- **Disk-space**: The 1st pass does not compress anything, meaning that you will need at least the
  equivalent of currently used space by your Nextcloud as available disk-space.  
  If you have no disk-space available, you can setup your instance to directly store your backup on an
  external storage:
    - the data generated during the 1st pass are not encrypted, Your data leaves the internal data folder
      from your instance and are now available on an external storage.
    - the 1st-pass will require more resource and your instance will stays in maintenance mode for a
      longer time.
    - If your external storage is not a local folder, huge network resources will be required.


- **Temporary Files**: during the 2nd pass (packing process), the compression and encryption require the
  creation of temporary files. while those files are temporary and deleted when they become useless, they
  are still available for few seconds. Meaning that the temp directory should not be shared with other
  application.


- **Export your setup**: If the option is not disable, Backups are encrypted with a key that is stored in
  the database of your current instance of Nextcloud. The key is mandatory to recover any data from your backups.
  
  You can export your setup from the Admin Settings/Backup page, or using `occ`. If encrypted, the export process will 
  generate  and returns its own key that will be required during the import when restoring your instance.
  As an admin, you will need to store the export file and its key, preferably in different location.
  

<a name="occ"></a>
## Available `occ` commands:

### Manage remote instance to store your backups remotely

You can upload your backup files on a remote instance

**Add a remote instance to store your backups**

    ./occ backup:remote:add cloud.example.net

**List configured remote instance**

    ./occ backup:remote:list

**Remove remote instance from the list**

    ./occ backup:remote:remove cloud.example.net

**Note**: if you enable the backup on remote instance, it is strongly advice
to [keep your current setup somewhere](), or your files won't be available without your identity nor
readable without your encryption key

### Manage your restoring point

**Create a new Restoring Point**

    ./occ backup:point:create [--incremental]

The `--incremental` option will create an incremental backup

**Upload a Restoring Point**

    ./occ backup:point:upload <pointId>

This will request all configured remote instances to check the sanity of any previous upload for this
Restoring Point, and will only upload missing/faulty file.

**List restoring points**

    ./occ backup:point:list

You can search and compare restoring point available locally and on configured remote instance.

**Search for a specific file:**

    ./occ backup:file:search [--since|--until|--point] <string>

Search for a file, based on its name.

example: `./occ backup:file:search test.jpg --since 2021-09-23`

**History of specific a file:**

    ./occ backup:file:history [--since|--until] <dataPack> <fullPath>

Display the history of a file.

example: `./occ backup:file:history data cult/files/backup1.md`

**Import a Restoring Point**

If you start using the app, you will face at one point a situation where an important Restoring Point is
available somewhere but cannot be find in your database. As an example, when restoring a Backup, all
Restoring Point created after this backup won't be in database anymore. This is normal as restoring the
backup fully overwrite your database. In that case, you can run this command:

    ./occ backup:point:scan <pointId>

If it cannot be found, you will need to manually copy the folder that contains the Restoring Point in the
appdata folder: data/appdata_qwerty123/backup/

**Verify integrity of a Restoring Point**

    ./occ backup:point:details <pointId>

## Restoration

You can restore a single file or the whole instance to a previous state:

    ./occ backup:point:restore <pointId> [--file <filename>] [--data <dataPack>] [--chunk chunkName]

Please note that you can go back to a previous backup of your instance from any Nextcloud compatible with
the Backup App. There is no need to install the exact same version as it will be reverted to the one used
when creating the Restoring Point. Meaning that you can fully restore your instance of Nextcloud even if
you lost your harddrive, as long as you kept a copy of the Restoring Point (upload to another remote
instance)

## Exporting configuration

This is an important step of your configuration of the Backup App Some information will be needed in case
you start storing your backup on remote instances:

- The identity of your Nextcloud,
- The encryption key used to encrypt your backup.

**While the identity can be changed and your access to the remote files can be restored by executing some
command on the remote instance to update your new identity, a missing encryption key means that your
remote backup cannot be decrypted and are totally useless.**

**Please note that creating a new identity will disable the sanity check on the metadata file.**

    ./occ backup:setup:export [--key] > ~/backup_setup.json

Using the `--key` option will generate a Key, used to encrypt/decrypt the data of your setup. The key
generated during the export of your setup needs to be stored somewhere safe!

    ./occ backup:setup:import [--key <key>] < ~/backup_setup.json

### Known issues, missing features:

- files are not encrypted when uploading to a remote instance
- cannot upload restoring point with file bigger than 100M
- uploading a parent RP after a dependant incremental backup does not remove the 'orphan' tag
- Importing a Restoring Point using `backup:point:scan` from an external folder
- Add remote instance to `backup:point:details`
