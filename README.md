# Backup

This App allows admin to create backup images of their Nextcloud


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

A restoring point is an image of your Nextcloud at a specific time. 
A restoring point can be:
- a 'full' backup that would contains all your local data files, 
- an 'incremental' backup that only contains modified files since your last backup.

**Create a new Restoring Point**

    ./occ backup:point:create [--incremental]

The `--incremental` option will only create an incremental backup


**Upload a Restoring Point**

    ./occ backup:point:upload <pointId>

**List restoring points (local and/or remote)**

    ./occ backup:point:list

**Search for a specific file:**

    ./occ backup:point:search [--since|--until|--point] <string>

example: `./occ backup:point:search test.jpg --since 2021-09-23`

### Known issues:

- cannot upload restoring point with file bigger than 100M
- uploading a parent RP after a dependant incremental backup does not remote the 'orphan' tag

