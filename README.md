<img src="https://github.com/italia/spid-graphics/blob/master/spid-logos/spid-logo-b-lb.png" alt="SPID" data-canonical-src="https://github.com/italia/spid-graphics/blob/master/spid-logos/spid-logo-b-lb.png" width="50%" />

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.2.5-8892BF.svg)](https://php.net/)
[![Join the #spid-php channel](https://img.shields.io/badge/Slack%20channel-%23spid--php-blue.svg?logo=slack)](https://developersitalia.slack.com/messages/CB6DCK274)
[![Get invited](https://slack.developers.italia.it/badge.svg)](https://slack.developers.italia.it/)
[![SPID on forum.italia.it](https://img.shields.io/badge/Forum-SPID-blue.svg)](https://forum.italia.it/c/spid)
![SP SPID in produzione con spid-php](https://img.shields.io/badge/SP%20SPID%20in%20produzione%20con%20spid--php-25+-green)
# Informazioni Generali - Jef - Lorenzo Acciarri
Questa repository è relativa ad un sistema PHP per l'autenticazione con Spid secondo OAuth 2.1.

La seguente sezione è utile ad illustrare le varie problematiche che si sono presentate durante l'integrazione di spid-php. Tale libreria ha comportato difficoltà di installazione
in ambiente locale con l'utilizzo di docker e per tale motivo l'applicazione è stata portata per un primo momento sul server demo di Jef,
per poi essere trasferita su una istanza EC2.



# Installazione su server EC2(Amazon Elastic Compute Cloud)
# Configurazione del server EC2
```
sudo yum update
sudo amazon-linux-extras disable php7.2
sudo amazon-linux-extras disable php7.3
sudo amazon-linux-extras enable nginx1 php7.4
yum install nginx php php-{fpm,mcrypt,xml,zip,curl,pdo,mysqlnd,mbstring} openssl11 git -y
sudo systemctl restart php-fpm
sudo yum clean metadata
sudo systemctl enable nginx
sudo systemctl start nginx
```
Installazione dei certificati SSL
```
cd /home/ec2-user/
wget -O epel.rpm –nv https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm
sudo yum install -y ./epel.rpm
sudo yum install python2-certbot-nginx.noarch
```

**Configurazione di nginx**:
Dentro la configurazione di nginx (/etc/nginx/nginx.conf), dentro il blocco http e non all'inizio della configurazione, aggiungiamo:

```
include /etc/nginx/sites-enabled/*;
```

```
mkdir /etc/nginx/sites-available
mkdir /etc/nginx/sites-enabled
touch /etc/nginx/sites-available/spid.audaxpa.it
```

Aggiungiamo al file appena creato la seguente configurazione:

**ATTENZIONE:** il **nome_endpoint_servizio** deve essere lo stesso che si inserisce durante l'istallazione di spid-php, in quanto nginx instradata direttamente la richiesta HTTP a quella location senza passare per il Slim. Nel caso in cui i due valori non coincidano si potrebbe avere un errore di Slim in quando appunto nginx non riconosce quella rotta che si stà chiamando.

**ATTENZIONE: IL PATH DELLA SOCK DI FPM POTREBBE NON ESSERE QUELLO DELLA CONFIGURAZIONE PRECEDENTE**
```
server {
  server_name  spid.audaxpa.it;
  root         /var/www/html/spid-php/public;

  error_page 404 /404.html;
  location = /404.html {
  }
  error_page 500 502 503 504 /50x.html;
  location = /50x.html {
  }
  location / {
    try_files $uri /index.php$is_args$args;
    location ~ \.php$ {
    fastcgi_pass "unix:///var/run/php-fpm/www.sock";
    fastcgi_index index.php;
    include /etc/nginx/fastcgi.conf;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
  }

  location /nome_endpoint_servizio/ {
    index index.php;
    location ~ \.php(/|$) {
      fastcgi_split_path_info ^(.+?\.php)(/.+)$;
      try_files $uri $fastcgi_script_name =404;
      set $path_info $fastcgi_path_info;
      fastcgi_param PATH_INFO $path_info;
      fastcgi_pass "unix:///var/run/php-fpm/www.sock";
      fastcgi_index index.php;
      include /etc/nginx/fastcgi.conf;
    }
  }
}
```

```
sudo ln -s /etc/nginx/sites-available/spid.audaxpa.it /etc/nginx/sites-enabled/
nginx -t
systemctl restart nginx
```

A questo punto la configurazione di nginx dovrebbe essere completata e il comando **nginx -t** permette di verificare lo stato di nginx.

Installiamo i certificati, **QUESTA AZIONE NECESSITA CHE SIA STATO AGGANCIATO IL DOMINIO AL SERVER**:
```
certbot
```

finita l'istallazione avremo i certificati installati.

Installiamo composer
```
sudo php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
sudo php composer-setup.php
sudo php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
```

**ATTENZIONE** da questo momento in poi tutte le azioni necessitano di permessi sui particolari 
file / cartelle, come ad esempio la configurazione di nginx oppure la cartella html, per tale motivo in ambiente di produzione devono essere **rivisti per un fattore di sicurezza**, ovvero **non usare l'utente ec2-ser**.

```
sudo chown -R ec2-user /var/www/html
```

#Installazione e configurazione del progetto
Spid CNF è un prodotto due diverse librerie: spid-php e oath-server.
La prima gestisce tutto il flusso di autorizzazione dell'utente tramite spid, mentre, la seconda, serve per la creazione di un server di autenticazione tramite standart OAuth 2.1.

#Configurazione repository e installazione della libreira spid-php
Scarichiamo il progetto da git hub:
```
cd /var/www/html
git clone https://github.com/italia/spid-php.git
cd spid-php
sudo mkdir /var/www/html/spid-php/public
cd /var/www/html/spid-php
touch .env
```
Andiamo a caricare all'interno della cartella **/var/www/html**, che è appena diventata la nostra cartella root, la libreria spid-php. 
Ed infine creiamo una directory per i log(creazione manuale per una prima fase, ipotizzare di crearla tramite php successivamente). 
**TODO**: eliminare questa seziome in quanto è utile solo in una prima fase di sviluppo.
```
sudo mkdir /var/www/html/logs-spid-access
```
**Premessa:**
1- La cwd da impostare è : **/var/www/html/spid-php**
2- Il path del web server invece è : **/var/www/html/spid-php/public**

**Ricordare che in fare di installazione della libreria spid-php la mancata compilazione di alcuni campi potrebbe portare ad errori** in particolare per quanto riguarda **i campi dell'organizzazione**.
**ATTENZIONE**: Spid-php necessita di openssl v.1.1.1, in caso non sia possibile installare tale versione è possibile sostituire all'interno del file **setup/Setup.php** la stringa "openssl"
con "openssl11" (due uno) che permetterà appunto di installare i certificati utilizzando openssl 1.1.1.



Installiamo la libreria:
```
composer install
```

A questo punto la libreria php è stata configurata e nella cartella **public** è stato inserito un collegamento al servizio per la gestione del nostro **metadata** con lo stesso nome 
che abbiamo fornito in fase di installazione.

Durante tale fase ci verranno chieste le informazioni del service provider, secondo accordo con CNF queste sono:

* Nome dell'endpoint del servizio: **spidaudaxpait**
* Nome del Service Provider: **CNF Spid Provider**
* Descrizione del Service Provider: **CNF Spid Provider Provider è un sistema di autenticazione spid per l'accesso ai servizi online dedicati alle PA**
* Nome dell'organizzazione: **Credit Network & Finance SpA**
* Nome mostrato dell'organizzazione: **Credit Network & Finance SpA**
* URL dell'organizzazione: **https://www.cienneffe.com/**
* Azienda identificata per partita iva o codice fiscale? **PARTITA IVA**
* Identificativo della Partita iva o del codice fiscale sulla base del punto precedente: **IT05863840962**
* Codice ID per il Cessionario Committente(è la partita iva): **IT05863840962**
* Denominazione per il Cessionario Committente: **Credit Network & Finance SpA**
* Via del Cessionario Committente: **Via Flavio Gioia**
* Civico del Cessionario Committente: **39**
* Cap del Cessionario Committente: **37135**
* Comune del Cessionario Committente: **Verona**
* Provincia del Cessionario Committente: **Verona**
* Nome dell'organizzazione per il cessionario committente: **Credit Network & Finance SpA**
* Email dell'organizzazione per il cessionario committente:  **info@cienneffe.com**
* Numero di telefono dell'organizzazione per il cessionario committente: **+390458760011**
* Nome località dell'organizzazione:  **Verona**
* Indice del servizio di consumo: **Quello di default**
* Email di contatto dell'organizzazione: **giovanni.racioppi@cienneffe.com**
* Numero di telefono dell'organizzazione **+390458760011**

**ATTENZIONE:** La partita IVA deve comprendere il codice paese(IT) e i vari contatti non devono comprendere spazi

# **Creazione del certificato CSR**
In fase di registraione come SP Privato è stata richiesta come da Avviso Agid n.23 il certificato CSR. Comando utile per la creazione del certificato CSR sulla base della chiave privata utilizzata per segnare il metadata:
```
openssl x509 -in spid-php-sp.crt  -signkey spid-php-sp.pem  -x509toreq -out spid-php-sp.csr
```
E' necessario anche l'hash tramite algoritmo SHA-256:
```
openssl dgst -sha256 spid-sp.csr
```

# PHP OAuth 2.0 Server
Spid CNF utilizza [PHP OAuth 2.0 Server](https://github.com/thephpleague/oauth2-server), basato sullo standard OAuth, per autorizzare i propri utenti. Tale libreria è basata sul framework **slim**.

La libreria OAuth 2.0 Server necessita di certificati openssl, generati automaticamente tramite il comando "**composer install**" appena eseguito.

Nel caso in cui i certificati non siano stati generati con successo allora per farlo manualmente lanciare:
```
composer install
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout > public.key
```

# Lo storage
Fino a quando il progetto è stato ospitato nel server demo di Jef lo storage dei dati è stato gestito attraverso un database MySQL, finchè non è stata fatta la migrazione su istanza EC2.
A questo punto è stato creato un database Aurora basato su MySQL tramite il servizio RDS.
**ATTENZIONE** il server EC2 non può connettersi al database RDS se non esplicitamente indicato nel **gruppo di sicurezza**.

Il driver MySQL è pdo in quanto mysqli su CentOS EC2 può essere installato tramite repository esterna e si è optato di utilizzare pdo, che è integrato direttamente dentro a amazon-linux-extras.
# spid-php
Software Development Kit for easy SPID access integration with SimpleSAMLphp.

spid-php has been developed and is maintained by Michele D'Amico (@damikael). **It's highly recommended to use the latest release**.

spid-php è uno script composer (https://getcomposer.org/) che semplifica e automatizza il processo di installazione e configurazione di SimpleSAMLphp (https://simplesamlphp.org/) per l'integrazione dell'autenticazione SPID all'interno di applicazioni PHP tramite lo SPID SP Access Button (https://github.com/italia/spid-sp-access-button). spid-php permette di realizzare un Service Provider per SPID in pochi secondi. **Si raccomanda di mantenere sempre aggiornata la propria installazione all'ultima versione**.

Durante il processo di setup lo script richiede l'inserimento delle seguenti informazioni:
* directory di installazione (directory corrente)
* directory root del webserver
* nome del link al quale collegare SimpleSAMLphp
* EntityID del service provider
* Tipologia del service provider (pubblico o privato)
* Informazioni del service provider da inserire nel metadata
* Informazioni del service provider da inserire nel certificato di firma in accordo con quanto previsto dall'[Avviso SPID n°29 v3](https://www.agid.gov.it/sites/default/files/repository_files/spid-avviso-n29v3-specifiche_sp_pubblici_e_privati.pdf) 
* AttributeConsumingServiceIndex da richiedere all'IDP
* Attributi richiesti da inserire nel metadata
* se inserire nella configurazione i dati dell'IDP SPID Demo (https://demo.spid.gov.it)
* se inserire nella configurazione i dati dell'IDP SPID Demo in modalità Validator (https://demo.spid.gov.it/validator)
* se inserire nella configurazione i dati dell'IDP per la validazione di AgID (https://validator.spid.gov.it)
* se copiare nella root del webserver i file di esempio per l'integrazione del bottone
* se copiare nella root del webserver i file di esempio per l'utilizzo come proxy
* le informazioni necessarie per configurare un client di esempio per il proxy
* i dati per la generazione del certificato X.509 per il service provider

e si occupa di eseguire i seguenti passi:
* scarica l'ultima versione di SimpleSAMLphp con le relative dipendenze
* scarica l'ultima versione dello spid-sp-access-button
* crea un certificato X.509 per il service provider
* scarica i metadata degli IDP di produzione tramite il metadata unico di configurazione (https://registry.spid.gov.it/metadata/idp/spid-entities-idps.xml)
* effettua tutte le necessarie configurazioni su SimpleSAMLphp
* predispone il template e le risorse grafiche dello SPID SP Access Button per essere utilizzate con SimpleSAMLphp

Al termine del processo di setup si potrà scaricare il [metadata](#Metadata) oppure utilizzare il certificato X.509 creato nella directory /cert per registrare il service provider sull'ambiente di test/validazione.

Se si è scelto di copiare i file di esempio, sarà possibile verificare subito l'integrazione accedendo da web a /login-spid.php.

Se si è scelto di copiare i file di esempio come proxy, sarà possibile verificare il funzionamento come proxy accedendo da web a /proxy-sample.php oppure /proxy-login-spid.php

## Requisiti
* Web server
* php >= 7.2.5 < 8.0
* php-xml
* php-mbstring
* Composer (https://getcomposer.org)
* OpenSSL >= 1.1.1
* OpenSSL aes-256-cbc Cipher Algorithm

## Installazione
```
# composer install
```
Al termine dell'installazione tutte le configurazioni sono salvate nei file *spid-php-setup.json*, *spid-php-openssl.cnf* e *spid-php-proxy.json*. In caso di reinstallazione, le informazioni di configurazione saranno recuperate automaticamente da tali file, senza la necessità di doverle reinserire nuovamente.  

## Disinstallazione
```
# composer uninstall
```
La disinstallazione non cancella gli eventuali file *spid-php-setup.json*, *spid-php-openssl.cnf* e *spid-php-proxy.json* locali che contengono le configurazioni inserite durante il processo in installazione.

## Aggiornamento Metadata IdP
```
# composer update-metadata
```

## Reinstallazione / Aggiornamento
```
# composer uninstall
# composer install
```
Se nella directory locale sono presenti i file *spid-php-setup.json* e *spid-php-openssl.cnf*, l'aggiornamento non richiederà nuovamente le informazioni di configurazione. Per modificare le informazioni di configurazione precedentemente inserite, occorrerà eseguire la disinstallazione, cancellare o modificare manualmente il file *spid-php-setup.json*, quindi procedere alla nuova installazione. Per rigenerare i certificati occorrerà eseguire la disinstallazione, rinominare o cancellare la directory /cert o i certificati *spid-sp.crt* e *spid-sp.pem* in essa presenti, quindi procedere ad una nuova installazione.

## Configurazione nginx
Per utilizzare SimpleSAMLphp su webserver nginx occorre configurare nginx come nell'esempio seguente.
In *myservice* inserire il nome del servizio come specificato durante l'installazione.

```
server {  
  listen 443 ssl http2;  
  server_name sp.example.com;
  root /var/www;
  include snippets/snakeoil.conf;  
  
  location / {
    try_files $uri $uri/ =404;
    index index.php;
    location ~ \.php$ {
      include snippets/fastcgi-php.conf;  
      fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;  
    }
  }
  
  location /myservice/ {
    index index.php;
    location ~ \.php(/|$) {
      fastcgi_split_path_info ^(.+?\.php)(/.+)$;
      try_files $fastcgi_script_name =404;
      set $path_info $fastcgi_path_info;
      fastcgi_param PATH_INFO $path_info;
      fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;  
      fastcgi_index index.php;
      include fastcgi.conf;
    }
  }
}
```

## Metadata
Dopo aver completato la procedura di installazione il metadata del service provider è scaricabile alla seguente url:

**/*myservice*/module.php/saml/sp/metadata.php/service**

dove *myservice* è il nome del servizio come specificato durante l'installazione.

## API SDK
### Costruttore
```
new SPID_PHP()
```

### isAuthenticated
```
bool isAuthenticated()
```
restituisce true se l'utente è autenticato, false altrimenti

### isIdPAvailable
```
bool isIdPAvailable($idp)
```
restituisce true se il valore di $idp è tra quelli previsti (vedi login) 

### isIdP
```
bool isIdP($idp)
```
restituisce true se l'utente è autenticato con l'idp $idp (vedi login)

### requireAuth
```
void requireAuth()
```
richiede che l'utente sia autenticato. Se l'utente non è autenticato mostra il pannello di scelta dell'IDP

### insertSPIDButtonCSS
```
void insertSPIDButtonCSS()
```
inserisce i riferimenti css necessari per la presentazione del bottone 

### insertSPIDButtonJS
```
void insertSPIDButtonJS()
```
inserisce i riferimenti al codice javascript necessario al funzionamento del bottone

### insertSPIDButton
```
void insertSPIDButton(string $size)
```
stampa il codice per l'inserimento dello smart button. $size specifica la dimensione del pulsante (S|M|L|XL)

### setPurpose
```
void setPurpose(string $purpose)
```
imposta l'estensione "Purpose" nell'AuthenticationRequest per inviare una richiesta di autenticazione per identità digitale ad uso professionale ([Avviso SPID n.18 v.2](https://www.agid.gov.it/sites/default/files/repository_files/spid-avviso-n18_v.2-_autenticazione_persona_giuridica_o_uso_professionale_per_la_persona_giuridica.pdf)).
$purpose specifica il valore dell'estensione Purpose (P|LP|PG|PF|PX)

### login
```
void login($idp, $level, [$returnTo], [$attributeConsumingServiceIndex])
```
invia una richiesta di login livello $level verso l'idp $idp. Dopo l'autenticazione, l'utente è reindirizzato alla url eventualmente specificata in $returnTo. Se il parametro $returnTo non è specificato, l'utente è reindirizzato alla pagina di provenienza.

$idp può assumere uno dei seguenti valori:
* VALIDATOR
* DEMO
* DEMOVALIDATOR
* ArubaPEC S.p.A.
* InfoCert S.p.A.
* IN.TE.S.A. S.p.A.
* Lepida S.p.A.
* Namirial
* Poste Italiane SpA
* Sielte S.p.A.
* Register.it S.p.A.
* TI Trust Technologies srl

$level può assumere uno dei seguenti valori
* 1
* 2
* 3

### getResponseID
```
string getResponseID()
```
restituisce l'attributo ID della SAML Response ricevuta dall'IdP

### getAttributes
```
array getAttributes()
```
restituisce gli attributi dell'utente autenticato

### getAttribute
```
string getAttribute(string $attribute)
```
restituisce il valore per lo specifico attributo 

### logout
```
void logout([$returnTo])
```
esegue la disconnessione. Dopo la disconnessione, l'utente è reindirizzato alla url specificata in $returnTo oppure alla pagina di provenienza se $returnTo non è specificato.

### getLogoutURL
```
string getLogoutURL([$returnTo])
```
restituisce la url per eseguire la disconnessione. Dopo la disconnessione, l'utente è reindirizzato alla url specificata in $returnTo oppure alla pagina di provenienza se $returnTo non è specificato.


## Esempio di integrazione
```
require_once("<path to spid-php>/spid-php.php");

$spidsdk = new SPID_PHP();

if(!$spidsdk->isAuthenticated()) {
    if(!isset($_GET['idp'])) {
        $spidsdk->insertSPIDButtonCSS();
        $spidsdk->insertSPIDButton("L");       
        $spidsdk->insertSPIDButtonJS();         
    } else {
        $spidsdk->login($_GET['idp'], 1);                
    }
} else {
    foreach($spidsdk->getAttributes() as $attribute=>$value) {
        echo "<p>" . $attribute . ": <b>" . $value[0] . "</b></p>";
    }
    
    echo "<hr/><p><a href='" . $spidsdk->getLogoutURL() . "'>Logout</a></p>";
}


```

## Utilizzo come Proxy
Durante l'installazione è possibile scegliere se installare i file di esempio per l'utilizzo di spid-php come proxy. 

In tal caso viene richiesto:
- URL di redirect alla quale inviare i dati dell'utente al termine dell'autenticazione
- se firmare la response
- se cifrare la response

Se si seleziona Y alla domanda se firmare la response, i dati dell'utente saranno inviati alla URL di redirect in POST contenuti in un token in formato JWS firmato con la chiave privata del certificato del Service Provider generato durante l'installazione e salvato in */cert*.

Se non si seleziona Y alla domanda se firmare la response, i dati dell'utente saranno inviati, invece, alla URL di redirect in POST come variabili in chiaro.

Se oltre alla firma, si seleziona Y anche alla domanda se cifrare la response, questa sarà inviata alla URL di redirect in POST come token in formato JWS il cui payload contiene nell'attributo *data* un token JWE cifrato con un *client_secret* contenente i dati dell'utente.

*client_id* e *client_secret* sono generati automaticamente per il client durante il setup, mostrati a video e salvati nel file *spid-php-proxy.json*.


Per inserire ulteriori client con le relative configurazioni di *client_id*, *client_secret* e *redirect_uri*, è possibile editare il file *spid-php-proxy*.json 

## API Proxy
### login
```
GET /proxy-spid.php?action=login&client_id=<client_id>&redirect_uri=<redirect_uri>&idp=<idp>&state=<state>
```
Invia la AuthnRequest ad uno specifico IdP e ritorna la Response decodificata o come JWS o JWS(JWE) in POST alla redirect_uri del client.

Parametri:

 - *client_id* è l'identificativo corrispondente al client
 - *redirect_uri* è la URL del client alla quale ritornare la risposta. Deve corrispondere ad una delle URL registrate per il client
 - *idp* è il valore che identifica l'IdP con il quale eseguire l'autenticazione (vedi API SDK login)
 - *state* è il valore del RelayState

### logout
```
GET /proxy-spid.php?action=logout&client_id=<client_id>
```
Esegue la disconnessione dall'IdP.
  
Parametri:
  
 - *client_id* è l'identificativo corrispondente al client. Dopo aver eseguito la disconnessione presso l'IdP, il flusso viene rediretto al primo redirect_uri registrato per il client

### verify
```
GET /proxy-spid.php?action=verify&token=<token>&decrypt=<decrypt>&secret=<secret>
```
Verifica e decifra il token JWT ricevuto con la response.
  
Parametri:
  
 - *token* è il token JWT ricevuto con la response, nel caso in cui si sia scelto di firmare la response
 - *decrypt* può assumere valore Y o N. Permette di decifrare la response nel caso in cui si sia scelto di cifrare la response
 - *secret* è il client_secret consegnato al client con cui è possibile decifrare la response
  
Nel caso in cui non è possibile verificare la firma oppure non è possibile decifrare la response (perchè il secret non è corretto oppure perchè il token è malformato) viene restituito status code 422.
  
Si consiglia di utilizzare l'endpoint verify esclusivamente per la verifica della firma dei messaggi. Per decifrare i dati dell'utente, invece, si consiglia di implementare la decodifica sul client.

## Esempio di utilizzo del proxy
```
<a href="/proxy-login-spid.php">Go to Login Page or...</a><br/>
<a href="/proxy-spid.php?client_id=<client_id>&action=login&redirect_uri=/proxy-sample.php&idp=DEMOVALIDATOR&state=state">Login with a single IdP (example for DEMO Validator)</a>
<p>
    <?php
        foreach($_POST as $attribute=>$value) {
            echo "<p>" . $attribute . ": <b>" . $value . "</b></p>";
        }
    ?>
</p>
<a href="/proxy-spid.php?client_id=61504487f292e&action=logout">Esci</a>

```
  
## Author
[Michele D'Amico (damikael)](https://it.linkedin.com/in/damikael)
 
## Credits
<a href="https://www.linfaservice.it" target="_blank"><img  style="border:1px solid #ccc; padding: 5px; height: 50px; margin-right: 20px;" src="https://www.linfaservice.it/common/linfaservice.png" alt="Linfa Service"></a>
<a href="https://www.manydesigns.com" target="_blank"><img  style="border:1px solid #ccc; padding: 5px; height: 50px; margin-right: 20px;" src="https://www.manydesigns.com/img/logoMD.png" alt="ManyDesigns"></a>
  
## Contributors
<a href = "https://github.com/italia/spid-php/contributors">
  <img src = "https://contrib.rocks/image?repo=italia/spid-php"/>
</a>

## Compliance

|<img src="https://github.com/italia/spid-graphics/blob/master/spid-logos/spid-logo-c-lb.png?raw=true" width="100" /><br />_Compliance with [SPID regulations](http://www.agid.gov.it/sites/default/files/circolari/spid-regole_tecniche_v1.pdf) (for Service Providers)_|status|notes|
|:---|:---|:---|
|**SPID Avviso n.18 v.2:**|✓||
|**SPID Avviso n.29 v.3:**|✓||
|generation of certificates|✓||
|generation of metadata|✓||
|**Metadata:**|||
|parsing of IdP XML metadata (1.2.2.4)|✓||
|parsing of AA XML metadata (2.2.4)|||
|SP XML metadata generation (1.3.2)|✓||
|**AuthnRequest generation (1.2.2.1):**|||
|generation of AuthnRequest XML|✓||
|HTTP-Redirect binding|✓||
|HTTP-POST binding|✓||
|`AssertionConsumerServiceURL` customization|||
|`AssertionConsumerServiceIndex` customization|||
|`AttributeConsumingServiceIndex` customization|✓||
|`AuthnContextClassRef` (SPID level) customization|✓||
|`RequestedAuthnContext/@Comparison` customization|✓||
|`RelayState` customization (1.2.2)|✓||
|**Response/Assertion parsing**|||
|verification of `Response/Signature` value (if any)|✓||
|verification of `Response/Signature` certificate (if any) against IdP/AA metadata|✓||
|verification of `Assertion/Signature` value|✓||
|verification of `Assertion/Signature` certificate against IdP/AA metadata|✓||
|verification of `SubjectConfirmationData/@Recipient`|✓||
|verification of `SubjectConfirmationData/@NotOnOrAfter`|✓||
|verification of `SubjectConfirmationData/@InResponseTo`|✓||
|verification of `Issuer`|✓||
|verification of `Destination`|✓||
|verification of `Conditions/@NotBefore`|✓||
|verification of `Conditions/@NotOnOrAfter`|✓||
|verification of `Audience`|✓||
|parsing of Response with no `Assertion` (authentication/query failure)|✓||
|parsing of failure `StatusCode` (Requester/Responder)|✓||
|verification of `RelayState` (saml-bindings-2.0-os 3.5.3)|✓||
|**Response/Assertion parsing for SSO (1.2.1, 1.2.2.2, 1.3.1):**|||
|parsing of `NameID`|✓||
|parsing of `AuthnContextClassRef` (SPID level)|✓||
|parsing of attributes|✓||
|**Response/Assertion parsing for attribute query (2.2.2.2, 2.3.1):**|||
|parsing of attributes|✓||
|**LogoutRequest generation (for SP-initiated logout):**|||
|generation of LogoutRequest XML|✓||
|HTTP-Redirect binding|✓||
|HTTP-POST binding|✓||
|**LogoutResponse parsing (for SP-initiated logout):**|||
|parsing of LogoutResponse XML|✓||
|verification of `LogoutResponse/Signature` value (if any)|✓||
|verification of `LogoutResponse/Signature` certificate (if any) against IdP metadata|✓||
|verification of `Issuer`|✓||
|verification of `Destination`|✓||
|PartialLogout detection|||
|**LogoutRequest parsing (for third-party-initiated logout):**||
|parsing of LogoutRequest XML|✓||
|verification of `LogoutRequest/Signature` value (if any)|✓||
|verification of `LogoutRequest/Signature` certificate (if any) against IdP metadata|✓||
|verification of `Issuer`|✓||
|verification of `Destination`|✓||
|parsing of `NameID`|✓||
|**LogoutResponse generation (for third-party-initiated logout):**||
|generation of LogoutResponse XML|✓||
|HTTP-Redirect binding|✓||
|HTTP-POST binding|✓||
|PartialLogout customization|✓||
|**AttributeQuery generation (2.2.2.1):**||
|generation of AttributeQuery XML|||
|SOAP binding (client)|||

