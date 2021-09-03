Work (and discussion) in Progress 

### The Backup App

- The App should be able to store an image of an instance of Nextcloud at a 
  specific point in Time.
- The App should be able to create: 
  - a complete copy of all local files,
  - a copy of the last modified local files,
  - the current setup of the Nextcloud and its apps,
  - a dump of database used by Nextcloud,
  - upload backup files to another instance of Nextcloud.
    
- The App will NOT create a copy of remote files, or files defined as remote (different filesystem)
- The App should be able to restore:
  - all or a part of the local files from a specific Time (one file, one folder),
  - a complete dump of the database from a specific Time,
  - a complete installation of Nextcloud from a specific Time.  


### Resources and Restoring Point

We will call Restoring Point (or RP) the backup created at a specific Time to which a complete image of the instance of Nextcloud can be restored in
its old state.

The main issue faced by the App is that, in order to keep local files and database synchronized, the instance will require the instance 
to be in maintenance mode.

To avoid a heavy load of the instance of Nextcloud and its database while providing 
a descent frequency of Restoring Point, the App will follow those step, shows in a timeline:

```
====1==<delay>==2===3===4==<delay>==5==<delay>==6===7=========================================================== timeline =>
    |           |   |   |           |           |   |
    |           |   |   |           |           |   \- if step2 have not been executed since n days go to step2
    |           |   |   |           |           |
    |           |   |   |           |           \- is step1 have not been executed since n weeks go to step1 
    |           |   |   |           |
    |           |   |   |           \- Uploading backup files to remote instances
    |           |   |   |    
    |           |   |   \- Completing the step2 by storing files modified since step2 (Creation of RP)
    |           |   |
    |           |   \- Dump of mysql Database
    |           |
    |           \- Storing files modified since the last RP
    |
    \- Storing all local files and database (Creation of RP), better to have a maintenance mode enabled 
```


### Features

This is the full list of basic features that will make the Backup App usable. Some
of those feature are already available and will only needs to be confirmed as finished.


- [ ] Managing Restoring Point
  - [ ] Creation of a Restoration Point based on a full backup
  - [ ] Creation of a Restoration Point based on an incremental backup  
  - [ ] Browsing of local Restoring Points
  - [ ] Browsing files from any Restoring Point
  - [ ] Extraction of a full backup with a complete restoration script: download of Nextcloud, 
    import of database and extraction of data
  - [ ] Extraction and Restoration of a part of the data from a Restoring Point 
    
- [ ] Managing Remote Instances
  - [ ] Authentication based on Webfinger and Signatory key pair
  - [ ] Adding, listing, updating and removing remote instances
    
- [ ] Storing Restoring Point
  - [ ] Upload of Restoring Points on remote instances
  - [ ] Encryption using libsodium and single key. The single key might needs to be encrypted 
    with a password to be store somewhere   
  - [ ] Browsing remote Restoring Point
  - [ ] Download of Restoring Point

