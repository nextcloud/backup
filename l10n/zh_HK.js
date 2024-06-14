OC.L10N.register(
    "backup",
    {
    "Backup" : "備份",
    "Update on all Backup's event" : "更新所有備份的事件",
    "complete" : "完整",
    "partial" : "局部的",
    "seconds" : "秒",
    "minutes" : "分鐘",
    "hours" : "小時",
    "days" : "日",
    "A new restoring point ({type}) has been generated, requiring maintenance mode for {downtime}." : "已生成新的還原點 ({type})，需要 {downtime} 的維護模式。",
    "Your system have been fully restored based on a restoring point from {date} (estimated rewind: {rewind})" : "您的系統已根據 {date} 的還原點完全還原（估計倒回：{rewind}）",
    "The file {file} have been restored based on a restoring point from {date} (estimated rewind: {rewind})" : "檔案 {file} 已根據 {date} 的還原點進行還原（估計倒回：{rewind}）",
    "Backup now. Restore later." : "立即備份。 稍後還原。",
    "The Backup App creates and stores backup images of your Nextcloud:\n\n- Backup the instance, its apps, your data and your database,\n- Administrator can configure the time slots for automated backup,\n- Full and Partial backup, with different frequencies,\n- 2-pass to limit downtime (maintenance mode) of your instance,\n- Compression and encryption,\n- Upload your encrypted backup on an external filesystem,\n- Download and search for your data,\n- Restore single file or the entire instance." : "備份應用程式創建並存儲 Nextcloud 的備份圖像：\n\n- 備份實例、其應用程序、您的數據和您的數據庫\n- 管理員可以配置自動備份的時間段\n- 完整和部分備份，具有不同的頻率\n- 2-pass 限制實例的停機時間（維護模式），\n- 壓縮和加密，\n- 將您的加密備份上傳到外部文件系統，\n- 下載並蒐索您的數據，\n- 恢復單個文件或整個實例",
    "App Data" : "應用程式數據",
    "Choose where the backup app will initially store the restoring points." : "選擇備份應用程式最初存儲還原點的位置。",
    "Path in which to store the data. (ex: app_data)" : "存儲數據的路徑。（如：app_data）",
    "Set as App Data" : "設為應用程式數據",
    "Error" : "錯誤",
    "Changing the App Data will delete the data stored in the previous one including restoring points." : "更改應用程式數據將刪除之前存儲的數據，包括還原點。",
    "I understand some data will be deleted." : "我明白某些數據將被刪除。",
    "Change the App Data" : "更改應用程式數據",
    "Local storage" : "近端儲存空間",
    "Unable to fetch app data" : "無法擷取應用程式數據",
    "App data has been set" : "應用程式數據已設置",
    "Unable to set app data" : "無法設置應用程式數據",
    "Restoring points locations" : "還原點位置",
    "Manage available storage locations for storing restoring points" : "管理用於存儲還原點的可用存儲位置",
    "Path in which to store the restoring points. (ex: backups)" : "存儲還原點的路徑。（如：backups）",
    "Add new external location" : "添加新外部位置",
    "External storage" : "外部儲存空間",
    "Restoring point location" : "還原點位置",
    "Actions" : "操作",
    "Delete" : "刪除",
    "No external storage available" : "沒有外部存儲可用",
    "If you want to store your restoring points on an external location, configure an external storage in the \"External storage\" app." : "如果要將還原點存儲在外部位置，請在“外部存儲”應用程式中配置外部存儲。",
    "No external locations set" : "未設置外部位置",
    "You can add a new location with the above form." : "您可以使用上述表格添加新位置。",
    "Unable to fetch external locations" : "無法擷取外部位置",
    "New external location added" : "已添加新的外部位置",
    "Unable to save new external location" : "無法保存新的外部位置",
    "External location deleted" : "已删除外部位置",
    "Unable to delete the external location" : "無法刪除外部位置",
    "Backups configuration" : "備份配置",
    "General configuration on how and when your restoring points are created." : "關於如何以及何時創建還原點的一般配置。",
    "Enable background tasks" : "啟用後台任務",
    "You can enable background task for backups. This means that the creation, maintenance and purges of backups will be done automatically." : "您可以為備份啟用後台任務。 這意味著備份的創建、維護和清除將自動完成。",
    "Creation: New restoring points will be created according to the schedule." : "創建：將根據時間表創建新的還原點。",
    "Maintenance: Restoring points will be packed and copied to potential external storages." : "維護：還原點將被打包並複製到潛在的外部存儲。",
    "Purge: Old restoring points will be deleted automatically according to the retention policy." : "清除：將根據保留策略自動刪除舊的還原點。",
    "Enable background tasks to automatically manage creation, maintenance and purge." : "啟用後台任務以自動管理創建、維護和清除。",
    "Backup schedule" : "備份時間表",
    "Limit restoring points creation to the following hours interval:" : "將還原點創建限制在以下小時間隔內：",
    "and" : "及",
    "Allow the creation of full restoring points during week day" : "允許在工作日創建完整還原點",
    "Time interval between two full restoring points" : "兩個完整還原點之間的時間距",
    "Time interval between two partial restoring points" : "兩個部分還原點之間的時間距",
    "Packing processing" : "打包處理",
    "Processing that will be done on the restoring points during the packing step." : "將在打包步驟期間在還原點上進行的處理。",
    "Encrypt restoring points" : "加密還原點",
    "Compress restoring points" : "壓縮還原點",
    "Retention policy" : "保留政策",
    "You can specify the number of restoring points to keep during a purge." : "您可以指定在清除期間要保留的還原點數。",
    "Policy for the local app data" : "近端應用數據政策",
    "Policy for external storages" : "外部儲存政策",
    "Export backup configuration" : "導出備份配置",
    "You can export your settings with the below button. The exported file is important as it allows you to restore your backup in case of full data lost. Keep it in a safe place!" : "您可以通過按下面的按鈕導出您的設置。 導出的文件很重要，因為它允許您在丟失完整數據的情況下恢復備份。 把它放在安全的地方！",
    "Export configuration" : "導出配置",
    "Your settings export as been downloaded encrypted. To be able to decrypt it later, please keep the following private key in a safe place:" : "您的設置導出已下載並加密。為了以後能夠解密，請將以下私鑰保存在安全的地方：",
    "Request the creation of a new restoring point now" : "立即請求創建一個新的還原點",
    "The creation of a restoring point has been requested and will be initiated soon." : "已請求創建還原點，並將很快啟動。",
    "Create full restoring point" : "創建完整還原點",
    "Requesting a backup will put the server in maintenance mode." : "請求備份將使伺服器處於維護模式。",
    "I understand that the server will be put in maintenance mode." : "我明白伺服器將進入維護模式。",
    "Cancel" : "取消",
    "Request {mode} restoring point" : "索取 {mode} 還原點",
    "Unable to fetch the settings" : "無法擷取設定",
    "Settings saved" : "設定已保存",
    "Unable to save the settings" : "無法保存設定",
    "Unable to request restoring point" : "無法索取還原點",
    "Unable to export settings" : "無法導出設定",
    "_A full restoring point will be created {delayFullRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._::_A full restoring point will be created {delayFullRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._" : ["將在一星期中的任何一天 {timeSlotsStart}:00 和 {timeSlotsEnd}:00 之間的最後一個還原點之後的 {delayPartialRestoringPoint} 天創建完整還原點。"],
    "_A full restoring point will be created {delayFullRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 during weekends._::_A full restoring point will be created {delayFullRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 during weekends._" : ["將在周末 {timeSlotsStart}:00 和 {timeSlotsEnd}:00 之間的最後一個還原點之後 {delayFullRestoringPoint} 天創建一個完整還原點。"],
    "_A partial restoring point will be created {delayPartialRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._::_A partial restoring point will be created {delayPartialRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._" : ["將在一星期中的任何一天 {timeSlotsStart}:00 和 {timeSlotsEnd}:00 之間的最後一個還原點之後的 {delayPartialRestoringPoint} 天創建部分還原點。"],
    "_day_::_days_" : ["日"],
    "Scheduled" : "預定",
    "Pending" : "待定",
    "Not completed" : "未完成",
    "Orphan" : "無主",
    "Completed" : "已完成",
    "Not packed yet" : "尚未集裝",
    "Packed" : "已集裝",
    "Encrypted" : "已加密",
    "Compressed" : "已被壓縮",
    "Restoring points history" : "還原點歷史",
    "List of the past and future restoring points" : "過去和未來還原點清單",
    "Issue" : "問題",
    "Health" : "健康度",
    "Status" : "狀態",
    "Date" : "日期",
    "ID" : "ID",
    "No issue" : "沒有任何問題",
    "Local" : "近端",
    "Next full restoring point" : "下個完整還原點",
    "Next partial restoring point" : "下個部分還原點",
    "Unable to fetch restoring points" : "無法擷取還原點",
    "Scan Backup Folder" : "掃描備份資料夾"
},
"nplurals=1; plural=0;");
