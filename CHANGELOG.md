# Changelog


### 1.0.4

- fixing an issue with some types of external storage,
- ignoring default apps/ folder when parsing custom_apps


### 1.0.3

- fixing a loop in DI


### 1.0.1

- ignore exception during the uninstall process if external appdata is not found
- adding a settings to enable/disable cron jobs. Should not break setup from 1.0.0
- adding `generate_logs` config flag to generate a log file within the backup folder when a backup, pack
  or upload process is initiated from cronjob
- lock cronjob jobs when running to avoid parallels process on big instance


### 1.0.0

First release for NC23
