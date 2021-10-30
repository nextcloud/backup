# Backup

This App creates and stores backup images of your Nextcloud.

- [Important notes](#notes)
- [Restoring Points](#restoring-point)
- [Hardware Requirement](#hardware)
- [How the Backup App manage your data](#backup-manage-data)
- [Export configuration](#export)
- [Important details about your data](#important)
- [Upload to External Storages](#external-storages)
- [AppData on External Storage](#external-appdata)
- [Available `occ` commands](#occ)

<a name="notes"></a>

## Important notes

- **Read the full documentation**,
- During the generation of the backup, the app will put your instance in `maintenance mode`,
- This app generates a lot of data and can fill your hard drive,
- By default **your data are encrypted**, meaning you **will need to export** the App configuration **as
  soon as possible** or you will **not** be able to **decrypt your backups**.

<a name="restoring-point"></a>

## Restoring Points

A restoring point is an image of your Nextcloud at a specific time. A restoring point can be:

- '**Full**' (or Complete) and contains a backup of :
    * the instance of Nextcloud,
    * the apps of the Nextcloud (`apps/` and `custom_apps/`),
    * A dump of the database,
    * the local folder defined as `data` of the Nextcloud.


- '**Partial**' (or Incremental) that contains a backup of :
    * the instance of Nextcloud,
    * the apps of the Nextcloud,
    * A dump of the database,
    * local data that have been modified/generated since the last **Full Backup**

### What data are available in a Restoring Point

Please note that the **Backup App** will not store ALL data from your Nextcloud. As an example, remote
files won't be stored.  
This is a list of what can be restored and what cannot be restored when using the **Backup App**:

A restoring point will store

- your current Nextcloud,
- the configuration in `config/config.php`,
- the `apps/` folder and any other `custom_apps/`
- your local `data/`, defined by `'datadirectory'` in `config/config.php`,
- original absolute paths,
- A full `sqldump` of your database,
- List of files and localisation within the backup.

A restoring point will **NOT** store:

- data from External Storages, even if the mounted filesystem is available locally.

### Metadata

A Restoring Point also contains a file named `restoring-point.data` that contains metadata about the
backup:

- Version of your Nextcloud,
- The ID of the parent backup in case of partial backup,
- The list of data file that compose the restoring point, the format for this data depends on the current
  status of the restoring point (packed/unpacked) and the settings (compression, encryption)
- Checksum for each files of the backup itself,
- The date of the restoring point,
- Comments,
- Information related to the health of the files during the last check.

While the file `restoring-point.data` confirm the integrity of all files and parts of the backup, it is
still possible to generate a restoring point based on the available files. However :

- there is no way to confirm the integrity of the restoring point,
- the restoring process will require some knowledge from the admin about the original infrastructure from
  the original instance that generated the backup.

**Generate Metadata from backup files**

- Upload the files of your restoring point on your instance of Nextcloud with the Backup App installed,
  in a **specific** folder in your Files.
- At the root of this **specific** folder, create a file named `restoring-point.data` and add this
  content inside:

       {"action": "generate", "id": "20211023234222-full-TFTBQewCEdcQ3cS"}

- Customize your `id`; while it is advised to use the correct **Id** of the **Restoring Point** (if
  known), any string would work. If kept empty, a new **Id** will be generated using the current time.

- Right-click the file `restoring-point.data` and select '`Scan Backup Folder`'

After few seconds, the metadata file will be generated and stored within the same `restoring-point.data`
itself.

<a name="hardware"></a>

## Hardware requirement

- **Diskspace**: Creating and storing backups require a lot, **a lot**, of disk-space.


- **AES Hardware Acceleration**: If your processor does not
  support [AES instruction set](https://en.wikipedia.org/wiki/AES_instruction_set), the encryption
  process will fall back to `aes-256-cbc`.  
  This should only affect you if using the Backup App to migrate your instance from an AES-supporting CPU
  to a non-AES-supporting CPU (ie. old arm proc). Enforcing the use of `aes-256-cbc` before the packing
  of the restoring point on a AES-supporting CPU will fix this:

    - run: `./occ config:app:set backup force_cbc --value '1' `
    - Pack the restoring point: `./occ backup:point:pack <pointId>`

<a name="backup-manage-data"></a>

## Configure the handle of your data

### The timing


From the **Admin Settings**/**Backup** page, you can configure the time slot and the rate for the
generation of your future backups.

![Settings Schedule](screenshots/settings_schedule.png)


The time slot define the time of the day the Backup App might generate a backup, it is based on the local
time on the server.

Keep in mind that your instance will be in `maintenance mode` for the duration of the generation of the
backup. This is the reason why, by default, **Full Backup** will only be started during the week-end,
while **Partial Backup** are also run during week days.

If you scroll down to the bottom of this page, you can have an estimation of the next backup based on
your settings:

![Settings Next Point](screenshots/settings_next_point.png)

### The first pass (the backup process)

During the **First Pass**, data are quickly stored in the `appdata` folder of the **Backup App*.   
At this point, there is no compression nor encryption; the first pass needs to be as fast as possible to
release the `maintenance mode` on the instance.   
The data are stored in a list of zip files (named `chunk`), each one with a maximum size of 4GB (unless
it contains a file bigger than 4GB).

Because there is no compression during the first pass, the `appdata` folder of the **Backup App** will
require at least the same size of your current setup of Nextcloud: the content of the `core`, its `apps`
and `local data`.

By default, the `appdata` folder of the Backup App is located in the same folder than the rest of the
data of your instance defined in `datadirectory`. It is estimated that the `Backup App` needs 65% of the
available diskspace of the `datadirectory`

In case there is no enough space, you can [#external-appdata](mount an External Storage) and move
the `appdata` folder of the **Backup app** there.

### The second pass (the packing process)

![Settings Packing](screenshots/settings_packing.png)

The second pass does not require to put your instance in `maintenance mode`. The 2nd pass consist in the
packing of the restoring point and eventually its upload on external storage.

The packing will list each `chunk` of your backup and:

- Compress them (if enabled),
- Split the result in multiple files (named `part`) of 100MB,
- Encrypt each `part` (if enabled),
- Once completed without issue, remove the original zip file of the `chunk` to free space.

<a name="export"></a>

### Storing on a different hard drive

![Settings External](screenshots/settings_upload.png)

Once packed, restoring points can be stored on a different hard drive. Locally or remotely.  
If [enabled and configured](#upload-to-external-storages), the Backup App will store and manage your
restoring points on the **External Storage**

## Exporting your configuration

![Settings export](screenshots/settings_export.png)

<a name="important"></a>

## Important details about your data

- **Disk-space**: The 1st pass does not compress anything, meaning that you will need at least the
  equivalent of currently used space by your Nextcloud as available disk-space. If you have no disk-space
  available, you can configure the app to use an external storage to store all its data.  
  The configuration process is described in [this chapter](#external-appdata).


- **Temporary Files**: during the 2nd pass (packing process), the compression and encryption require the
  creation of temporary files. while those files are temporary and deleted when they become useless, they
  are still available for few seconds. Meaning that the temp directory should not be shared with other
  application.


- **Export your setup**: If the option is not disable, Backups are encrypted with a key that is stored in
  the database of your current instance of Nextcloud. The key is mandatory to recover any data from your
  backups.

  You can export your setup from the Admin Settings/Backup page, or using `occ`. If encrypted, the export
  process will generate and returns its own key that will be required during the import when restoring
  your instance. As an admin, you will need to store the export file and its key, preferably in different
  location.


- **.nobackup**: The presence of a `.nobackup` file in a folder will exclude all content from the current
  folder and its subfolders at the creation of the backup.

<a name="external-storages"></a>

## Upload to External Storages

<a name="external-appdata"></a>

## AppData on External Storage

If you have no disk-space available, you can configure the app to use an external storage to store all
its data:

- the data generated during the 1st pass are not encrypted, Your data leaves the internal data folder
  from your instance and are now available on an external storage.
- the 1st-pass will require more resources and your instance will stays in maintenance mode for a longer
  time.
- If your external storage is not a local folder, huge network resources will be required.

run `./occ backup:external:appdata` and follow instruction to select the configured external storage, and
configure the path to the right folder.

<a name="occ"></a>

## Available `occ` commands:

### Export/Import the configuration of your app.

It is **mandatory** to export the configuration of the app as it contains the encryption keys for your
encrypted backup and you will not be able to restore your backups from a data lost.

You can do that from the Admin Settings page or using the `occ` command:

    ./occ backup:setup:export [--key] > ~/backup.setup

This will create the file `~/backup.setup`.  
When using the option `--key` the setup will be encrypted and an `encryption_key` will be generated and
returned by the occ command. This key needs to be stored somewhere and will be required to decrypt the
saved configuration.  
It is strongly (again) advised to use the `--key` option

To restore the exported configuration:

     ./occ backup:setup:import [--key encryption_key] < ~/backup.setup

It is **mandatory** to export the configuration of the app as it contains the encryption keys for your
encrypted backup and you will not be able to restore your backups from a data lost.

You can do that from the Admin Settings page or using the `occ` command:

    ./occ backup:setup:export [--key] > ~/backup.setup

This will create the file `~/backup.setup`.  
When using the option `--key` the setup will be encrypted and an `encryption_key` will be generated and
returned by the occ command. This key needs to be stored somewhere and will be required to decrypt the
saved configuration.  
It is strongly (again) advised to use the `--key` option

To restore the exported configuration:

     ./occ backup:setup:import [--key encryption_key] < ~/backup.setup

### Manage your restoring point

**Create a new Restoring Point**

While this is managed by a background job, you can still generate a restoring point manually:

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
