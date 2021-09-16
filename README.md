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

    ./occ backup:point:create [--quick]

The `--quick` option will only create an incremental backup


**Upload a Restoring Point**

    ./occ backup:point:upload <pointId>

**Browse your restoring points**

    ./occ backup:point:browse
