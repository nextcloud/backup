OC.L10N.register(
    "backup",
    {
    "Backup" : "Biztonsági mentés",
    "Update on all Backup's event" : "Frissítés minden biztonsági mentési eseménynél",
    "complete" : "teljes",
    "partial" : "részleges",
    "seconds" : "másodperc",
    "minutes" : "perc",
    "hours" : "óra",
    "days" : "nap",
    "A new restoring point ({type}) has been generated, requiring maintenance mode for {downtime}." : "Új helyreállítási pont ({type}) előállítva, a szükséges karbantartási idő: {downtime}.",
    "Your system have been fully restored based on a restoring point from {date} (estimated rewind: {rewind})" : "A rendszer teljesen vissza lett állítva a {date}.-i helyreállítási pontból (becsült helyreállítás: {rewind})",
    "The file {file} have been restored based on a restoring point from {date} (estimated rewind: {rewind})" : "A(z) {file} fájl teljesen vissza lett állítva a {date}.-i helyreállítási pontból (becsült helyreállítás: {rewind})",
    "Backup now. Restore later." : "Biztonsági mentés most. Helyreállítás később.",
    "The Backup App creates and stores backup images of your Nextcloud:\n\n- Backup the instance, its apps, your data and your database,\n- Administrator can configure the time slots for automated backup,\n- Full and Partial backup, with different frequencies,\n- 2-pass to limit downtime (maintenance mode) of your instance,\n- Compression and encryption,\n- Upload your encrypted backup on an external filesystem,\n- Download and search for your data,\n- Restore single file or the entire instance." : "A Biztonsági mentés alkalmazás biztonsági lemezképeket hoz létre és tárol a Nextcloudjáról:\n\n- Biztonsági mentés készítése a példányról, az alkalmazásokról, az adatairól és az adatbázisáról,\n- A rendszergazdák beállíthatják az automatizált biztonsági mentés időszakait,\n- Teljes és részleges biztonsági mentés, eltérő gyakoriságokkal,\n- Kettős menet a példány leállási idejének (karbantartási módjának) korlátozásához,\n- Tömörítés és titkosítás,\n- Titkosított biztonsági mentés feltöltése külső fájlrendszerre,\n- Adatok letöltése és keresése,\n- Egyetlen fájl vagy a teljes példány helyreállítása.",
    "Local storage" : "Helyi tároló",
    "Unable to fetch app data" : "Nem sikerült lekérni az alkalmazásadatokat",
    "App data has been set" : "Az alkalmazásadatok beállítva",
    "Unable to set app data" : "Nem sikerült beállítani az alkalmazásadatokat",
    "App Data" : "Alkalmazásadatok",
    "Choose where the backup app will initially store the restoring points." : "Válassza ki, hogy a biztonsági mentési alkalmazás kezdésként hol tárolja a helyreállítási pontokat.",
    "Path in which to store the data. (ex: app_data)" : "Elérési út, ahol az adatok tárolandók. (például: app_data)",
    "Set as App Data" : "Beállítás alkalmazásadatokként",
    "Error" : "Hiba",
    "Changing the App Data will delete the data stored in the previous one including restoring points." : "Az alkalmazásadatok módosítása törölni fogja az előzőben tárolt adatokat, köztük a helyreállítási pontokat is.",
    "I understand some data will be deleted." : "Értem, hogy bizonyos adatok törölve lesznek.",
    "Change the App Data" : "Alkalmazásadatok módosítása",
    "Unable to fetch external locations" : "Nem kérhetők le a külső helyek",
    "New external location added" : "Új külső hely hozzáadva",
    "Unable to save new external location" : "Nem menthető az új külső hely",
    "External location deleted" : "Külső hely törölve",
    "Unable to delete the external location" : "Nem törölhető a külső hely",
    "Restoring points locations" : "Helyreállítási pontok helyei",
    "Manage available storage locations for storing restoring points" : "Az helyreállítási pontok tárolásához elérhető tárolóhelyek",
    "Path in which to store the restoring points. (ex: backups)" : "Elérési út, ahol a helyreállítási pontok tárolandók. (például: backups)",
    "Add new external location" : "Új külső hely hozzáadása",
    "External storage" : "Külső tároló",
    "Restoring point location" : "Helyreállítási pontok helye",
    "Actions" : "Műveletek",
    "Delete" : "Törlés",
    "No external storage available" : "Nem érhető el külső tároló",
    "If you want to store your restoring points on an external location, configure an external storage in the \"External storage\" app." : "Ha külső helyen tárolná a helyreállítási pontjait, akkor állítson be külső tárolót a „Külső tároló” alkalmazással.",
    "No external locations set" : "Nincsenek külső helyek beállítva",
    "You can add a new location with the above form." : "Új helyet adhat hozzá a fenti űrlappal.",
    "Unable to fetch the settings" : "Nem sikerült lekérni a beállításokat",
    "Settings saved" : "Beállítások mentve",
    "Unable to save the settings" : "Nem sikerült menteni a beállításokat",
    "Unable to request restoring point" : "Nem sikerült a helyreállítási pont kérése",
    "Unable to export settings" : "Nem sikerült exportálni a beállításokat",
    "Backups configuration" : "Biztonsági mentések beállítása",
    "General configuration on how and when your restoring points are created." : "Általános beállítások a helyreállítási pontok létrehozásának módjáról és idejéről.",
    "Enable background tasks" : "Háttérfeladatok engedélyezése",
    "You can enable background task for backups. This means that the creation, maintenance and purges of backups will be done automatically." : "Engedélyezheti a biztonsági mentések háttérfeladatait. Ezt azt jelenti, hogy a biztonsági mentések létrehozása, karbantartása és törlése automatikusan el lesz végezve.",
    "Creation: New restoring points will be created according to the schedule." : "Létrehozás: Az új helyreállítási pontok az ütemterv szerint lesznek létrehozva.",
    "Maintenance: Restoring points will be packed and copied to potential external storages." : "Karbantartás: A helyreállítási pontok össze lesznek csomagolva, és át lesznek másolva a lehetséges külső tárolókra.",
    "Purge: Old restoring points will be deleted automatically according to the retention policy." : "Törlés: A régi visszaállítási pontok automatikusan törölve lesznek a megtartási házirend alapján.",
    "Enable background tasks to automatically manage creation, maintenance and purge." : "Engedélyezés, hogy a háttérfeladatok automatikusan kezeljék a létrehozást, karbantartást és törlést.",
    "Backup schedule" : "Biztonsági mentési ütemterv",
    "Limit restoring points creation to the following hours interval:" : "Helyreállítási pontok létrehozásának korlátozása a következő időszakban:",
    "and" : "és",
    "Allow the creation of full restoring points during week day" : "A teljes helyreállítási pontok létrehozásának engedélyezése hétköznapokon",
    "Time interval between two full restoring points" : "Időintervallum két teljes helyreállítási pont között",
    "Time interval between two partial restoring points" : "Időintervallum két részleges helyreállítási pont között",
    "Packing processing" : "Csomagolás utáni feldolgozás",
    "Processing that will be done on the restoring points during the packing step." : "A helyreállítási pontok létrehozásának csomagolási lépése után végrehajtandó feldolgozás.",
    "Encrypt restoring points" : "Helyreállítási pontok titkosítása",
    "Compress restoring points" : "Helyreállítási pontok tömörítése",
    "Retention policy" : "Megtartási házirend",
    "You can specify the number of restoring points to keep during a purge." : "Megadhatja a törléskor megtartandó helyreállítási pontok számát.",
    "Policy for the local app data" : "A helyi alkalmazásadatok házirendje",
    "Policy for external storages" : "Külső tárolók házirendje",
    "Export backup configuration" : "Biztonsági mentési beállítások exportálása",
    "You can export your settings with the below button. The exported file is important as it allows you to restore your backup in case of full data lost. Keep it in a safe place!" : "A lenti gombbal exportálhatja a beállításait. Az exportált fájl fontos, mert lehetővé teszi a biztonsági mentés helyreállítását teljes adatvesztéskor. Tartsa biztonságban.",
    "Export configuration" : "Konfiguráció exportálása",
    "Your settings export has been downloaded encrypted. To be able to decrypt it later, please keep the following private key in a safe place:" : "A beállítások exportja titkosítva lett letöltve. Hogy később is fel tudja oldani, tartsa biztonságban a privát kulcsot:",
    "Request the creation of a new restoring point now" : "Új helyreállítási pont létrehozásának kérése most",
    "The creation of a restoring point has been requested and will be initiated soon." : "A helyreállítási pont létrehozását kérték, és hamarosan elindul.",
    "Create full restoring point" : "Teljes helyreállítási pont létrehozása",
    "Requesting a backup will put the server in maintenance mode." : "A biztonsági mentés kérése karbantartási módba állítja a kiszolgálót.",
    "I understand that the server will be put in maintenance mode." : "Értem, hogy a kiszolgáló karbantartási módba fog kerülni.",
    "Cancel" : "Mégse",
    "Request {mode} restoring point" : "{mode} helyreállítási pont kérése",
    "_A full restoring point will be created {delayFullRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._::_A full restoring point will be created {delayFullRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._" : ["Egy teljes helyreállítási pont lesz létrehozva {delayFullRestoringPoint} nappal az előző után, {timeSlotsStart}:00 é {timeSliosEnd}:00 között, a hét bármely napján.","Egy teljes helyreállítási pont lesz létrehozva {delayFullRestoringPoint} nappal az előző után, {timeSlotsStart}:00 é {timeSliosEnd}:00 között, a hét bármely napján."],
    "_A full restoring point will be created {delayFullRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 during weekends._::_A full restoring point will be created {delayFullRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 during weekends._" : ["Egy teljes helyreállítási pont lesz létrehozva {delayFullRestoringPoint} nappal az előző után, {timeSlotsStart}:00 é {timeSliosEnd}:00 között, hétvégente.","Egy teljes helyreállítási pont lesz létrehozva {delayFullRestoringPoint} nappal az előző után, {timeSlotsStart}:00 é {timeSliosEnd}:00 között, hétvégente."],
    "_A partial restoring point will be created {delayPartialRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._::_A partial restoring point will be created {delayPartialRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._" : ["Egy részleges helyreállítási pont lesz létrehozva {delayPartialRestoringPoint} nappal az előző után, {timeSlotsStart}:00 é {timeSliosEnd}:00 között, a hét bármely napján.","Egy részleges helyreállítási pont lesz létrehozva {delayPartialRestoringPoint} nappal az előző után, {timeSlotsStart}:00 é {timeSliosEnd}:00 között, a hét bármely napján."],
    "_day_::_days_" : ["nap","nap"],
    "Scheduled" : "Ütemezve",
    "Pending" : "Függőben",
    "Not completed" : "Nincs kész",
    "Orphan" : "Elárvult",
    "Completed" : "Kész",
    "Not packed yet" : "Még nincs csomagolva",
    "Packed" : "Csomagolva",
    "Encrypted" : "Titkosítva",
    "Compressed" : "Tömörítve",
    "Unable to fetch restoring points" : "Nem sikerült a helyreállítási pontok lekérése",
    "Restoring points history" : "Helyreállítási pontok előzményei",
    "List of the past and future restoring points" : "Múltbeli és jövőbeli helyreállítási pontok",
    "Issue" : "Probléma",
    "Health" : "Egészség",
    "Status" : "Állapot",
    "Date" : "Dátum",
    "ID" : "Azonosító",
    "No issue" : "Nincs probléma",
    "Local" : "Helyi",
    "Next full restoring point" : "Következő teljes helyreállítási pont",
    "Next partial restoring point" : "Következő részleges helyreállítási pont",
    "Scan Backup Folder" : "Biztonsági mentés mappájának átvizsgálása"
},
"nplurals=2; plural=(n != 1);");
