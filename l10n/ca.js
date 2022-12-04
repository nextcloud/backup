OC.L10N.register(
    "backup",
    {
    "Scan Backup Folder" : "Escaneja la carpeta de còpia de seguretat",
    "Backup" : "Fes una còpia de seguretat",
    "Update on all Backup's event" : "Actualització de tots els esdeveniments de Còpia de seguretat",
    "complete" : "complert",
    "partial" : "parcial",
    "seconds" : "segons",
    "minutes" : "minuts",
    "hours" : "hores",
    "days" : "dies",
    "A new restoring point ({type}) has been generated, requiring maintenance mode for {downtime}." : "S'ha generat un nou punt de restauració ({type}), que requereix un mode de manteniment per a {downtime}.",
    "Your system have been fully restored based on a restoring point from {date} (estimated rewind: {rewind})" : "El vostre sistema ha estat restablert completament en base a un punt de restauració de {date} (retrocés estimat: {rewind})",
    "The file {file} have been restored based on a restoring point from {date} (estimated rewind: {rewind})" : "El fitxer {file} s'ha restaurat en funció d'un punt de restauració de {date} (rebobinat estimat: {rewind})",
    "Backup now. Restore later." : "Feu una còpia de seguretat ara. Restaura més tard.",
    "The Backup App creates and stores backup images of your Nextcloud:\n\n- Backup the instance, its apps, your data and your database,\n- Administrator can configure the time slots for automated backup,\n- Full and Partial backup, with different frequencies,\n- 2-pass to limit downtime (maintenance mode) of your instance,\n- Compression and encryption,\n- Upload your encrypted backup on an external filesystem,\n- Download and search for your data,\n- Restore single file or the entire instance." : "L'aplicació Còpia de seguretat crea i emmagatzema imatges de còpia de seguretat del vostre Nextcloud:\n\n- Feu una còpia de seguretat de la instància, les seves aplicacions, les vostres dades i la vostra base de dades,\n- L'administrador pot configurar les franges horàries per a la còpia de seguretat automatitzada,\n- Còpia de seguretat completa i parcial, amb diferents freqüències,\n- Dues passades per limitar el temps d'inactivitat (mode de manteniment) de la vostra instància,\n- Compressió i xifratge,\n- Carregueu la vostra còpia de seguretat xifrada en un sistema de fitxers extern,\n- Baixeu i cerqueu les vostres dades,\n- Restaura un fitxer únic o la instància sencera.",
    "App Data" : "Dades d'aplicacions",
    "Choose where the backup app will initially store the restoring points." : "Trieu on l'aplicació de còpia de seguretat guardarà inicialment els punts de restauració.",
    "Path in which to store the data. (ex: app_data)" : "Ruta on s'emmagatzemen les dades. (ex: dades_aplicació)",
    "Set as App Data" : "Definir com Dades d'Aplicació",
    "Error" : "Error",
    "Changing the App Data will delete the data stored in the previous one including restoring points." : "Si canvieu les Dades d'Aplicació, se suprimiran les dades emmagatzemades a l'anterior, inclosos els punts de restauració.",
    "I understand some data will be deleted." : "Entenc que algunes dades es suprimiran.",
    "Change the App Data" : "Canvia Dades d'Aplicació",
    "Local storage" : "Emmagatzematge loca",
    "Unable to fetch app data" : "Incapaç d'obtenir dades d'aplicació",
    "App data has been set" : "S'han configurat les dades d'aplicacions",
    "Unable to set app data" : "No es poden definir les dades d'aplicació",
    "Restoring points locations" : "Ubicacions de punts de restauració",
    "Manage available storage locations for storing restoring points" : "Gestioneu les ubicacions d'emmagatzematge disponibles per emmagatzemar els punts de restauració",
    "Path in which to store the restoring points. (ex: backups)" : "Ruta on emmagatzemar els punts de restauració. (ex: còpies de seguretat)",
    "Add new external location" : "Afegir nova localització externa",
    "External storage" : "Emmagatzematge extern",
    "Restoring point location" : "Ubicació del punt de restauració",
    "Actions" : "Accions",
    "Delete" : "Suprimeix",
    "No external storage available" : "No hi ha emmagatzematge extern disponible",
    "If you want to store your restoring points on an external location, configure an external storage in the \"External storage\" app." : "Si voleu emmagatzemar els vostres punts de restauració en una ubicació externa, configureu un emmagatzematge extern a l'aplicació \"Emmagatzematge extern\".",
    "No external locations set" : "No s'han definit localitzacions externes",
    "You can add a new location with the above form." : "Podeu afegir una nova ubicació amb el formulari anterior.",
    "Unable to fetch external locations" : "Incapaç d'obtenir localitzacions externes",
    "New external location added" : "Nova localització externa afegida",
    "Unable to save new external location" : "No es pot desar la nova ubicació externa",
    "External location deleted" : "S'ha suprimit la ubicació externa",
    "Unable to delete the external location" : "No es pot suprimir la ubicació externa",
    "Backups configuration" : "Configuració de còpies de seguretat",
    "General configuration on how and when your restoring points are created." : "Configuració general sobre com i quan es creen els vostres punts de restauració.",
    "Enable background tasks" : "Habilita les tasques en segon pla",
    "You can enable background task for backups. This means that the creation, maintenance and purges of backups will be done automatically." : "Podeu habilitar la tasca en segon pla per a les còpies de seguretat. Això vol dir que la creació, manteniment i purgues de còpies de seguretat es faran automàticament.",
    "Creation: New restoring points will be created according to the schedule." : "Creació: Es crearan nous punts de restauració segons la planificació.",
    "Maintenance: Restoring points will be packed and copied to potential external storages." : "Manteniment: els punts de restauració s'empaquetaran i es copiaran a possibles emmagatzematges externs.",
    "Purge: Old restoring points will be deleted automatically according to the retention policy." : "Purga: els punts de restauració antics se suprimiran automàticament d'acord amb la política de retenció.",
    "Enable background tasks to automatically manage creation, maintenance and purge." : "Habiliteu les tasques en segon pla per gestionar automàticament la creació, el manteniment i la purga.",
    "Backup schedule" : "Planificació de còpies de seguretat",
    "Limit restoring points creation to the following hours interval:" : "Limiteu la creació de punts de restauració a l'interval d'hores següent:",
    "and" : "i",
    "Allow the creation of full restoring points during week day" : "Permetre la creació de punts de restauració complets durant el dia de la setmana",
    "Time interval between two full restoring points" : "Interval de temps entre dos punts de restauració completa",
    "Time interval between two partial restoring points" : "Interval de temps entre dos punts de restauració parcial",
    "Packing processing" : "Processament d'empaquetat",
    "Processing that will be done on the restoring points during the packing step." : "Processament que es farà als punts de restauració durant l'etapa d'empaquetat.",
    "Encrypt restoring points" : "Xifrar els punts de restauració",
    "Compress restoring points" : "Comprimir els punts de restauració",
    "Retention policy" : "Política de retenció",
    "You can specify the number of restoring points to keep during a purge." : "Podeu especificar el nombre de punts de restauració que cal mantenir durant una purga.",
    "Policy for the local app data" : "Política per a les dades d'aplicació local",
    "Policy for external storages" : "Política d'emmagatzematge extern",
    "Export backup configuration" : "Exporta la configuració de còpia de seguretat",
    "You can export your settings with the below button. The exported file is important as it allows you to restore your backup in case of full data lost. Keep it in a safe place!" : "Podeu exportar la vostra paràmetres amb el botó següent. El fitxer exportat és important, ja que us permet restaurar la vostra còpia de seguretat en cas de pèrdua de dades completes. Guardeu-lo en un lloc segur!",
    "Export configuration" : "Exporta la configuració",
    "Your settings export as been downloaded encrypted. To be able to decrypt it later, please keep the following private key in a safe place:" : "Els teus paràmetres s'exporten tal com s'ha baixat xifrats. Per poder desxifrar-lo més tard, manteniu la clau privada següent en un lloc segur:",
    "Request the creation of a new restoring point now" : "Sol·liciteu ara la creació d'un nou punt de restauració",
    "The creation of a restoring point as been requested and will be initiated soon." : "La creació d'un punt de restauració tal com s'ha sol·licitat i s'iniciarà properament.",
    "Create full restoring point" : "Creeu un punt de restauració complet",
    "Requesting a backup will put the server in maintenance mode." : "Sol·licitar una còpia de seguretat posarà el servidor en mode de manteniment.",
    "I understand that the server will be put in maintenance mode." : "Entenc que el servidor es posarà en mode de manteniment.",
    "Cancel" : "Cancel·la",
    "Request {mode} restoring point" : "Sol·liciteu {mode} punt de restauració",
    "Unable to fetch the settings" : "No es poden recuperar els paràmetres",
    "Settings saved" : "S'han desat els paràmetres",
    "Unable to save the settings" : "No es poden desar els paràmetres",
    "Unable to request restoring point" : "No es pot sol·licitar el punt de restauració",
    "Unable to export settings" : "No es poden exportar els paràmetres",
    "_A full restoring point will be created {delayFullRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._::_A full restoring point will be created {delayFullRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._" : ["Es crearà un punt de restauració complet {delayFullRestoringPoint} dia després de l'últim entre les {timeSlotsStart}:00 i les {timeSlotsEnd}:00 qualsevol dia de la setmana.","Es crearà un punt de restauració complet {delayFullRestoringPoint} dies després de l'últim entre les {timeSlotsStart}:00 i les {timeSlotsEnd}:00 qualsevol dia de la setmana."],
    "_A full restoring point will be created {delayFullRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 during weekends._::_A full restoring point will be created {delayFullRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 during weekends._" : ["Es crearà un punt de restauració complet {delayFullRestoringPoint} dia després de l'últim entre les {timeSlotsStart}:00 i les {timeSlotsEnd}:00 durant els caps de setmana.","Es crearà un punt de restauració complet {delayFullRestoringPoint} dies després de l'últim entre les {timeSlotsStart}:00 i les {timeSlotsEnd}:00 durant els caps de setmana."],
    "_A partial restoring point will be created {delayPartialRestoringPoint} day after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._::_A partial restoring point will be created {delayPartialRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 any day of the week._" : ["Un punt de restauració parcial es crearà {delayPartialRestoringPoint} dia després de l'últim entre les {timeSlotsStart}:00 i les {timeSlotsEnd}:00 qualsevol dia de la setmana.","Es crearà un punt de restauració parcial {delayPartialRestoringPoint} dies després de l'últim entre les {timeSlotsStart}:00 i les {timeSlotsEnd}:00 qualsevol dia de la setmana."],
    "_day_::_days_" : ["dia","dies"],
    "Scheduled" : "Planificat",
    "Pending" : "Pendent",
    "Not completed" : "No completat",
    "Orphan" : "Orfe",
    "Completed" : "S'ha completat",
    "Not packed yet" : "Encara no empaquetat",
    "Packed" : "Empaquetat",
    "Encrypted" : "Xifrat",
    "Compressed" : "Comprimit",
    "Restoring points history" : "Restauració de l'historial de punts",
    "List of the past and future restoring points" : "Llista dels punts de restauració passats i futurs",
    "Issue" : "Error",
    "Health" : "Salut",
    "Status" : "Estat",
    "Date" : "Data",
    "ID" : "ID",
    "No issue" : "Cap error",
    "Local" : "Local",
    "Next full restoring point" : "Següent punt de restauració complerta",
    "Next partial restoring point" : "Següent punt de restauració parcial",
    "Unable to fetch restoring points" : "No s'han pogut recuperar els punts de restauració",
    "local" : "local"
},
"nplurals=2; plural=(n != 1);");
