<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Prenotazione';
$string['pluginadministration'] = 'Amministrazione prenotazione';

$string['reservation:grade'] = 'Sottomettere valutazioni';
$string['reservation:reserve'] = 'Effettuare prenotazioni personali';
$string['reservation:viewnote'] = 'Visualizzare le note delle prenotazioni';
$string['reservation:viewrequest'] = 'Visualizzare l\'elenco delle prenotazioni';
$string['reservation:manualreserve'] = 'Effettuare prenotazioni per altri utenti';
$string['reservation:manualdelete'] = 'Cancellare prenotazioni di altri utenti';
$string['reservation:downloadrequests'] = 'Scaricare l\'elenco delle prenotazioni';
$string['reservation:addinstance'] = 'Aggiungere una nuova prenotazione';
$string['reservation:uploadreservations'] = 'Effettuare upload prenotazioni';


$string['availablerequests'] = 'Posti disponibili';
$string['location'] = 'Luogo';
$string['otherlocation'] = 'Altro luogo specifica';
$string['cancelledon'] = 'Annullato il';
$string['cleanview'] = 'Visualizza solo gli iscritti';
$string['description'] = 'Descrizione';
$string['fullview'] = 'Visualizza anche le prenotazioni annullate';
$string['maxrequest'] = 'Numero massimo prenotazioni';
$string['configmaxrequests'] = 'Numero massimo di prenotazioni definito nel menu a tendina nella pagina di modifica';
$string['modulename'] = 'Prenotazione';
$string['modulenameplural'] = 'Prenotazioni';
$string['nolimit'] = 'Nessun limite';
$string['nomorerequest'] = 'Non ci sono posti disponibili';
$string['noreservations'] = 'Nessuna prenotazione visualizzabile';
$string['requests'] = 'Richieste';
$string['reservationcancelled'] = 'Prenotazione cancellata';
$string['reservationclosed'] = 'Prenotazioni chiuse';
$string['reservationdenied'] = 'Prenotazioni non permesse';
$string['reservationnotopened'] = 'Prenotazioni non ancora aperte';
$string['reservations'] = 'Prenotazioni';
$string['reserve'] = 'Prenota';
$string['reservecancel'] = 'Cancella Prenotazione';
$string['reserved'] = 'Prenotato';
$string['reservedon'] = 'Prenotato il';
$string['timeclose'] = 'Chiusura prenotazioni';
$string['timeopen'] = 'Apertura prenotazioni';
$string['timestart'] = 'Data inizio';
$string['timeend'] = 'Data termine';
$string['save'] = 'Salva valutazioni';
$string['justbooked'] = 'Prenotazione registrata.<br />Posizione: {$a}';
$string['alreadybooked'] = 'Prenotazione già effettuata';
$string['yourgrade'] = 'Il voto relativo a questa prenotazione &egrave;: {$a->grade}/{$a->maxgrade}';
$string['yourscale'] = 'Il voto relativo a questa prenotazione &egrave;: {$a}';
$string['by'] = 'da';
$string['showrequest'] = 'Elenco delle prenotazioni pubblico';
$string['configlocations'] = 'In questa pagina &egrave; possibile gestire i luoghi per le prenotazioni di questo sito Moodle';
$string['locations'] = 'Gestione Luoghi';
$string['locationslist'] = 'Elenco Luoghi';
$string['newlocation'] = 'Nuovo Luogo';
$string['resetreservation'] = 'Rimuovere tutte le';
$string['withselected'] = 'Con i selezionati...';
$string['note'] = 'Nota';
$string['enablenote'] = 'Utilizza note degli utenti';
$string['notopened'] = 'Non aperta';
$string['closed'] = 'Chiusa';
$string['fields'] = 'Campi mostrati';
$string['configfields'] = 'Questa impostazione definisce quali campi verranno mostrati nella tabella delle prenotazioni';
$string['config'] = 'Impostazioni generali modulo Prenotazioni';
$string['explainconfig'] = 'Gli amministratori possono impostare qui alcuni parametri generali per il modulo Prenotazioni';
$string['addparticipant'] = 'Aggiungi prenotazione';
$string['noteachers'] = 'Non ci sono docenti';
$string['reservationsettings'] = 'Impostazioni prenotazione';
$string['eventsettings'] = 'Impostazioni evento';
$string['sublimit'] = '{$a}&ordm; sottolimite prenotazioni';
$string['with'] = 'con';
$string['equal'] = 'uguale a';
$string['notequal'] = 'diverso da';
$string['sublimits'] = 'Sottolimiti prenotazioni';
$string['configsublimits'] = 'Definisce il numero di righe per le regole dei sottolimiti nella pagina di modifica';
$string['err_timeendlower'] = 'La data di termine dell\'evento &egrave; precedente a quella di inizio';
$string['err_timeopengreater'] = 'La data di apertura delle prenotazioni &egrave; oltre quella di chiusura';
$string['err_sublimitsgreater'] = 'La somma dei sottolimiti &egrave; superiore al numero massimo di prenotazioni';
$string['overbook'] = 'Overbooking';
$string['nooverbook'] = 'Nessun Overbooking';
$string['maxoverbook'] = 'Percentuale massima overbooking';
$string['configmaxoverbook'] = 'Questa impostazione definisce la percentuale massima di overbooking per le prenotazioni.';
$string['overbookstep'] = 'Passo overbooking';
$string['configoverbookstep'] = 'Questa impostazione definisce la granularità della percentuale di ovebooking. Minore il passo, maggiore la granularità';
$string['overbookonly'] = 'Disponibili solo posti in overbooking';
$string['requestoverview'] = 'Riassunto prenotazioni';
$string['sublimitrules'] = 'Definizione dei sottolimiti';
$string['selectvalue'] = 'Selezionare uno dei valori disponibili';
$string['novalues'] = 'Il campo selezionato non &egrave; stato definito per alcun utente';
$string['close'] = 'chiudi';
$string['manualusers'] = 'La prenotazione manuale mostra gli utenti del';
$string['configmanualusers'] = 'Questa impostazione definisce quale elenco di utenti viene mostrato nel menu a tendina utilizzato per la prenotazione manuale.';
$string['autohide'] = 'Nascondi le prenotazioni dagli elenchi';
$string['configautohide'] = 'Questa impostazione definisce quando le prenotazioni devono essere nascoste dalle liste (mod/reservation/index.php). Pu&ograve; essere utile quando si rende le liste pubbliche.';
$string['atstart'] = 'Dopo l\'inizio dell\'evento';
$string['after5min'] = '5 minuti dopo l\'inizio dell\'evento';
$string['after10min'] = '10 minuti dopo l\'inizio dell\'evento';
$string['after30min'] = '30 minuti dopo l\'inizio dell\'evento';
$string['after1h'] = '1 ora dopo l\'inizio dell\'evento';
$string['after2h'] = '2 ore dopo l\'inizio dell\'evento';
$string['after4h'] = '4 ore dopo l\'inizio dell\'evento';
$string['after6h'] = '6 ore dopo l\'inizio dell\'evento';
$string['after12h'] = '12 ore dopo l\'inizio dell\'evento';
$string['after1d'] = '1 giorno dopo l\'inizio dell\'evento';
$string['after2d'] = '2 giorni dopo l\'inizio dell\'evento';
$string['after1w'] = '1 settimana dopo l\'inizio dell\'evento';
$string['after2w'] = '2 settimane dopo l\'inizio dell\'evento';
$string['after3w'] = '3 settimane dopo l\'inizio dell\'evento';
$string['after4w'] = '4 settimane dopo l\'inizio dell\'evento';
$string['publiclists'] = 'Liste prenotazioni pubbliche';
$string['configpubliclists'] = 'Questa impostazione definisce se le liste delle prenotazioni sono pubbliche (visualizzabili senza effettuare login) o meno.';
$string['sortby'] = 'Liste delle prenotazioni ordinate per';
$string['configsortby'] = 'Questa impostazione definisce come devono essere ordinate le liste delle prenotazioni.';
$string['bysection'] = 'Argomento/Settimana';
$string['bydate'] = 'Data dell\'evento';
$string['byname'] = 'Nome';
$string['number'] = 'Numero Prenotazione';
$string['linenumber'] = '#';
$string['gradedmail'] = '{$a->teacher} ha inviato una valutazione per la tua
prenotazione \'{$a->reservation}\'

Per visualizzarlo puoi accedere qui:

    {$a->url}';
$string['gradedmailhtml'] = '{$a->teacher} ha inviato una valutazione per la tua prenotazione <em>{$a->reservation}</em><br /><br />
Per visualizzarlo puoi accedere <a href=\"{$a->url}\">qui</a>.';
$string['mail'] = 'La prenotazione \'{$a->reservation}\' si è chiusa.

Per scaricare l\'elenco delle prenotazioni puoi accedere a:

    {$a->url}';
$string['mailhtml'] = 'La prenotazione <em>{$a->reservation}</em> si &egrave; chiusa.<br /><br />
Per scaricare la lista delle prenotazioni puoi accedere <a href="{$a->url}">qui</a>.';
$string['mailrequest'] = 'La prenotazione \'{$a->reservation}\' si è chiusa.

Per conoscere il numero della tua prenotazione puoi accedere a:

    {$a->url}';
$string['mailrequesthtml'] = 'La prenotazione <em>{$a->reservation}</em> si &egrave; chiusa.<br /><br />
Per conoscere il numero della tua prenotazione puoi accedere <a href="{$a->url}">qui</a>.';
$string['configdownload'] = 'Questa impostazione definisce il formato standard per il download delle liste delle richieste e delle prenotazioni';
$string['configcheckclashes'] = 'Visualizza il bottone "Verifica concomitanze" nella pagina di modifica delle prenotazioni';
$string['checkclashes'] = 'Verifica concomitanze';
$string['clashesreport'] = 'Rapporto concomitanze';
$string['noclashes'] = 'Luogo e orario non in concomitanza con altre prenotazioni';
$string['clashesfound'] = 'Luogo o orario in concomitanza con altre prenotazioni';
$string['minduration'] = 'Durata minima eventi';
$string['configminduration'] = 'Questa impostazione definisce la durata minima degli eventi delle prenotazioni. Valore utilizzato per il controllo della disponibilit&agrave; negli eventi senza data di termine.';
$string['duration5min'] = '5 minuti';
$string['duration10min'] = '10 minuti';
$string['duration15min'] = '15 minuti';
$string['duration20min'] = '20 minuti';
$string['duration30min'] = '30 minuti';
$string['duration45min'] = '45 minuti';
$string['duration60min'] = '60 minuti';
$string['duration90min'] = '90 minuti';
$string['duration2h'] = '2 ore';
$string['duration3h'] = '3 ore';
$string['duration4h'] = '4 ore';
$string['duration5h'] = '5 ore';
$string['duration6h'] = '6 ore';
$string['duration7h'] = '7 ore';
$string['duration8h'] = '8 ore';
$string['duration9h'] = '9 ore';
$string['duration10h'] = '10 ore';
$string['duration11h'] = '11 ore';
$string['duration12h'] = '12 ore';

$string['upload'] = 'Upload prenotazioni';
$string['upload_help'] = '<p>Le prenotazioni possono essere caricate tramite un file di testo. Il formato del file deve essere il seguente:</p><ul><li>Ogni linea deve contenere un record</li><li>Ogni record è una serie di dati separati da virgole (o altro delimitatore)</li><li>Il primo record contiene una lista dei nomi dei campi che definiscono il formato del resto del file</li><li>I campi obbligatori sono section, name, timestart</li><li>I campi opzionali sono course, intro, teachers, timeend, maxgrade, timeopen, timeclose, maxrequest</li><li>Se il corso non è impostato dovrà essere selezionato nella pagina di anteprima, tutte le prenotazioni finiranno in un solo corso</li></ul>';
$string['uploadreservations'] = 'Upload';
$string['uploadreservationsresult'] = 'Rapporto upload prenotazioni';
$string['importreservations'] = 'Importa prenotazioni';
$string['uploadreservationspreview'] = 'Anteprima upload prenotazioni';
$string['badcourse'] = 'Il corso non esiste';
$string['badteachers'] = 'Il docente con indirizzo email ({$a}) non è stato trovato';
$string['badteachersmail'] = 'Indirizzo email ({$a}) non è valido';
$string['badsection'] = 'La sezione non esiste nel corso "{$a}"';
$string['badtimestart'] = 'timestart non è valido';
$string['badtimeend'] = 'timeend non è valido';
$string['badtimeopen'] = 'timeopen non è valido';
$string['badtimeclose'] = 'timeclose non è valido';
$string['nocourseswithnsections'] = 'Non sono esistono corsi con {$a} sezioni';
$string['parent'] = 'Collega questa prenotazione con';
$string['noparent'] = 'Nessuna';
$string['connectto'] = 'Prenotazioni collegabili dal';
$string['configconnectto'] = 'Definisce dove il modulo deve cercare le prenotazioni collegabili';
$string['connectedto'] = 'Prenotazione collegata con';
$string['reservedonconnected'] = 'Prenotazione già effettuata nella prenotazione collegata: {$a}';
$string['overview'] = 'Informazioni Generali';
$string['manage'] = 'Gestione Prenotazioni';
$string['confirmdelete'] = 'Sei sicuro di voler rimuovere le prenotazioni selezionate?';
$string['notifies'] = 'Invio Notifiche';
$string['confignotifies'] = 'Questa impostazione definisce quali notifiche devono essere inviate';
$string['notifyteachers'] = 'Notifica la chiusura delle prenotazioni ai docenti';
$string['notifystudents'] = 'Notifica la chiusura delle prenotazioni agli studenti';
$string['notifygrades'] = 'Notifica la modifica della valutazione agli studenti';
$string['events'] = 'Eventi del calendario';
$string['configevents'] = 'Questa impostazione definisce quali eventi del calendario saranno creati per ogni prenotazione';
$string['reservationevent'] = 'Crea un evento dalla data di apertura a quella di chiusura delle prenotazioni';
$string['eventevent'] = 'Crea un evento dalla data di inizio a quella di fine impostato nella prenotazione';
$string['downloadas'] = 'Formato di download predefinito';
$string['reservation_settings'] = 'Impostazioni di modifica';
$string['reservation_listing'] = 'Impostazioni per la pagina dell\'elenco delle prenotazioni del corso';
$string['reservation_view'] = 'Impostazioni della pagina di visualizzazione';
$string['reservation_other'] = 'Altre impostazioni';
$string['message'] = 'Messaggio ai partecipanti';
$string['eventrequestadded'] = 'Aggiunta richiesta di prenotazione';
$string['eventrequestcancelled'] = 'Annullata richiesta di prenotazione';
$string['eventrequestdeleted'] = 'Rimossa richiesta di prenotazione';
$string['modulename_help'] = '<p>L\'utilizzo principale di questo modulo &egrave; la prenotazione degli studenti alle sessioni di laboratorio e agli esami ma pu&ograve; essere utilizzato per qualunque tipo di prenotazione</p><p> Il docente pu&ograve; limitare il numero di posti disponibili per il determinato evento, la data dello stesso e le date di apertura e chiusura delle prenotazioni.</p><p>Ad ogni prenotazione pu&grave; essere assegnata una valutazione o una scala di valutazione</p><p>Gli studenti possono prenotarsi e cancellare le proprie prenotazioni aggiungendo eventualmente una nota testuale.</p><p>Dopo l\'inizio dell\'evento il docente potr&agrave; attribuire le valutazioni agli utenti prenotati. Una mail di notifica verr&agrave; inviata agli studenti.</p><p>La lista delle prenotazioni pu&ograve; essere scaricata in diversi formati di file</p>';
$string['completionreserved'] = 'Lo studente deve effettuare la prenotazione per completare l\'attività';
$string['badparent'] = 'Questa prenotazione era collegata con un\'altra che non &egrave; stata ripristinata adesso. Il collegamento &egrave; stato rimosso. Se necessario ricollegarle manualmente.';

?>
