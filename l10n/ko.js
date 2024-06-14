OC.L10N.register(
    "backup",
    {
    "Backup" : "백업",
    "Update on all Backup's event" : "모든 백업 이벤트를 업데이트합니다.",
    "complete" : "완성",
    "partial" : "일부분",
    "seconds" : "초",
    "minutes" : "분",
    "hours" : "시",
    "days" : "일",
    "A new restoring point ({type}) has been generated, requiring maintenance mode for {downtime}." : "새로운 복원 포인트 ({type})가 생성됐으며, {downtime}동안 유지 보수 모드를 요구합니다.",
    "Your system have been fully restored based on a restoring point from {date} (estimated rewind: {rewind})" : "당신의 시스템은 {date}의 복원 포인트를 기반으로 완전히 복원됐습니다. (추정 rewind: {rewind})",
    "The file {file} have been restored based on a restoring point from {date} (estimated rewind: {rewind})" : "파일 {file}은 {date}의 복원 포인트를 기반으로 완전히 복원됐습니다. (추정 rewind: {rewind})",
    "Backup now. Restore later." : "지금 백업하고, 복원은 나중에 하세요.",
    "The Backup App creates and stores backup images of your Nextcloud:\n\n- Backup the instance, its apps, your data and your database,\n- Administrator can configure the time slots for automated backup,\n- Full and Partial backup, with different frequencies,\n- 2-pass to limit downtime (maintenance mode) of your instance,\n- Compression and encryption,\n- Upload your encrypted backup on an external filesystem,\n- Download and search for your data,\n- Restore single file or the entire instance." : "백업 어플이 백업 이미지를 당신의 Nextcloud에 생성하고 저장합니다.\n\n- 인스턴스, 앱과 당신의 데이터, 그리고 당신의 데이터베이스를 백업합니다,\n- 관리자는 자동 백업을 위한 시간대를 구성할 수 있습니다,\n- 온전한 백업과 부분적인 백업, 서로 다른 빈도로 이루어집니다,\n- 당신의 인스턴스의 중단 시간(유지 모드)을 제한하는 2가지 방법,\n- 압축과 암호화,\n- 외부 파일 시스템에 당신의 암호화된 백업을 업로드 합니다,\n- 당신의 데이터를 검색하고 다운로드합니다,\n- 개별 파일 혹은 전체 인스턴스를 복원합니다.",
    "App Data" : "앱 데이터",
    "Choose where the backup app will initially store the restoring points." : "백업 앱이 최초로 복원 포인트를 저장할 장소를 고르세요.",
    "Path in which to store the data. (ex: app_data)" : "데이터를 저장하기 위한 통로. (예: app_data)",
    "Set as App Data" : "앱 데이터로 설정",
    "Error" : "오류",
    "Changing the App Data will delete the data stored in the previous one including restoring points." : "앱 데이터를 바꾸는 것은 복원 포인트를 포함한 이미 저장되어 있는 데이터를 지울 것입니다.",
    "I understand some data will be deleted." : "나는 몇몇 데이터가 지워질 것을 이해합니다.",
    "Change the App Data" : "앱 데이터를 바꿉니다.",
    "Local storage" : "로컬 저장소",
    "Unable to fetch app data" : "앱 데이터를 불러올 수 없습니다.",
    "App data has been set" : "앱 데이터가 저장됐습니다.",
    "Unable to set app data" : "앱 데이터를 설정할 수 없습니다.",
    "Restoring points locations" : "복원 포인트 위치",
    "Manage available storage locations for storing restoring points" : "복원 포인트를 저장하는 것이 가능한 저장소 위치를 관리합니다.",
    "Path in which to store the restoring points. (ex: backups)" : "복원 포인트를 저장하기 위한 통로. (예: backups)",
    "Add new external location" : "새로운 외부 저장소 추가",
    "External storage" : "외부 저장소",
    "Restoring point location" : "복원 포인트 위치",
    "Actions" : "동작",
    "Delete" : "삭제",
    "No external storage available" : "가능한 외부 저장소 없음",
    "If you want to store your restoring points on an external location, configure an external storage in the \"External storage\" app." : "만약 당신이 당신의 복원 포인트를 외부 저장소에 저장하고 싶다면, \"External storage\" app에서 외부 저장소를 구성하십시오.",
    "No external locations set" : "설정된 외부 저장소 없음",
    "You can add a new location with the above form." : "당신은 상단의 양식을 통해 새로운 위치를 추가할 수 있습니다.",
    "Unable to fetch external locations" : "외부 위치를 불러올 수 없음",
    "New external location added" : "새로운 외부 위치 추가됨",
    "Unable to save new external location" : "새로운 외부 위치를 저장할 수 없음",
    "External location deleted" : "외부 위치 삭제됨",
    "Unable to delete the external location" : "외부 위치를 삭제할 수 없음",
    "Backups configuration" : "백업 구성",
    "General configuration on how and when your restoring points are created." : "당신의 복원 포인트가 언제 어떻게 생성될 것인지에 관한 일반적인 구성",
    "Enable background tasks" : "백그라운드 업무를 허가 합니다.",
    "You can enable background task for backups. This means that the creation, maintenance and purges of backups will be done automatically." : "당신은 백업을 위해 백그라운드 업무를 허가할 수 있습니다. 이것은 백업의 생성, 유지, 그리고 일소가 자동적으로 이루어질 것을 의미합니다.",
    "Creation: New restoring points will be created according to the schedule." : "생성: 일정에 맞춰 새로운 복원 포인트가 생성될 것입니다.",
    "Maintenance: Restoring points will be packed and copied to potential external storages." : "유지 보수: 복원 지점은 사용 가능한 외부 저장소로 포장되고 복제 될 것입니다.",
    "Purge: Old restoring points will be deleted automatically according to the retention policy." : "일소:  낡은 복원 포인트는 보존 정책에 따라 자동적으로 삭제될 것입니다.",
    "Enable background tasks to automatically manage creation, maintenance and purge." : "생성, 유지, 그리고 일소를 자동적으로 관리하기 위해 백그라운드 업무를 허가합니다.",
    "Backup schedule" : "백업 일정",
    "Limit restoring points creation to the following hours interval:" : "이하의 시간 간격에 따라 복원 포인트 생성을 제한합니다.",
    "and" : "그리고",
    "Allow the creation of full restoring points during week day" : "평일 간 완전한 복원 포인트의 생성을 허락합니다.",
    "Time interval between two full restoring points" : "2개의 완전한 복원 포인트 사이의 시간 간격",
    "Time interval between two partial restoring points" : "2개의 부분적인 복원 포인트 사이의 시간 간격",
    "Packing processing" : "포장 처리",
    "Processing that will be done on the restoring points during the packing step." : "포장 단계에서 복원 포인트에 이루어지는 처리",
    "Encrypt restoring points" : "복원 포인트를 암호화합니다.",
    "Compress restoring points" : "복원 포인트를 압축합니다.",
    "Retention policy" : "보존 정책",
    "You can specify the number of restoring points to keep during a purge." : "당신은 일소 도중 유지할 복원 포인트의 개수를 지정할 수 있다. ",
    "Policy for the local app data" : "로컬 앱 데이터를 위한 정책",
    "Policy for external storages" : "외부 저장소를 위한 정책",
    "Export backup configuration" : "백업 구성을 내보냅니다.",
    "You can export your settings with the below button. The exported file is important as it allows you to restore your backup in case of full data lost. Keep it in a safe place!" : "당신은 하단의 버튼을 통해 당신의 설정을 내보낼 수 있습니다. 내보낸 파일은 당신이 모든 데이터를 잃어버렸을 때 백업을 복원할 수 있도록 사용되므로 중요합니다. 안전한 장소에 보관하십시오!",
    "Export configuration" : "구성을 내보냅니다.",
    "Your settings export as been downloaded encrypted. To be able to decrypt it later, please keep the following private key in a safe place:" : "당신의 설정은 암호화되어 다운로드 되도록 내보내집니다. 이것을 나중에 해독하기 위해서, 이하의 프라이빗 키를 안전한 장소에 보관해주십시오:   ",
    "Request the creation of a new restoring point now" : "지금 새로운 복원 포인트의 생성을 요청합니다.",
    "The creation of a restoring point has been requested and will be initiated soon." : "복원 포인트의 생성은 요청됐고 곧 시작할 것입니다.",
    "Create full restoring point" : "완전한 복원 포인트 생성",
    "Requesting a backup will put the server in maintenance mode." : "백업을 요청하면 서버가 유지 보수 모드가 됩니다.",
    "I understand that the server will be put in maintenance mode." : "나는 서버가 유지 보수 모드로 전환함을 이해합니다.",
    "Cancel" : "취소",
    "Request {mode} restoring point" : "{mode} 복원 포인트를 요청",
    "Unable to fetch the settings" : "설정을 불러올 수 없습니다.",
    "Settings saved" : "설정 저장됨",
    "Unable to save the settings" : "설정을 저장할 수 없습니다.",
    "Unable to request restoring point" : "복원 포인트를 요청할 수 없습니다.",
    "Unable to export settings" : "설정을 내보낼 수 없습니다.",
    "_A full restoring point will be created {delayFullRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._::_A full restoring point will be created {delayFullRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._" : ["완전한 복원 포인트는 {timeSlotsStart}:00에서 {timeSlotsEnd}:00 사이의 마지막 포인트로부터 최소 {delayFullRestoringPoint}일 후에 생성됩니다."],
    "_A full restoring point will be created {delayFullRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 during weekends._::_A full restoring point will be created {delayFullRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 during weekends._" : ["완전한 복원 포인트는 {timeSlotsStart}:00에서 {timeSlotsEnd}:00 사이의 마지막 포인트로부터 최소 {delayFullRestoringPoint}일이 지난 주말에 생성됩니다."],
    "_A partial restoring point will be created {delayPartialRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._::_A partial restoring point will be created {delayPartialRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._" : ["부분적인 복원 포인트는 {timeSlotsStart}:00에서 {timeSlotsEnd}:00 사이의 마지막 포인트로부터 최소 {delayFullRestoringPoint}일 후에 생성됩니다."],
    "_day_::_days_" : ["일"],
    "Scheduled" : "예정됨",
    "Pending" : "보류 중",
    "Not completed" : "완성되지 않았다",
    "Orphan" : "고아",
    "Completed" : "완료됨",
    "Encrypted" : "암호화",
    "Compressed" : "압축됨",
    "Restoring points history" : "복원 포인트 역사",
    "List of the past and future restoring points" : "과거와 미래 복원 포인트의 리스트",
    "Issue" : "문제점",
    "Health" : "건강",
    "Status" : "상태",
    "Date" : "날짜",
    "ID" : "ID",
    "No issue" : "문제점 없음",
    "Local" : "로컬",
    "Next full restoring point" : "다음 완전한 복원 포인트",
    "Next partial restoring point" : "다음 부분적인 복원 포인트",
    "Unable to fetch restoring points" : "복원 포인트를 불러올 수 없습니다.",
    "Scan Backup Folder" : "백업 폴더를 스캔합니다."
},
"nplurals=1; plural=0;");
