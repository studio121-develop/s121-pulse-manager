# S121 Pulse Manager

**Plugin WordPress per la gestione di servizi ricorrenti clienti, rinnovi automatici, promemoria e integrazione diretta con le API di Fatture in Cloud.**

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![Licenza](https://img.shields.io/badge/Licenza-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Indice

- [Panoramica](#panoramica)
- [Funzionalità](#funzionalità)
- [Architettura e Struttura del Progetto](#architettura-e-struttura-del-progetto)
- [Primi Passi](#primi-passi)
- [Configurazione](#configurazione)
- [Utilizzo](#utilizzo)
- [Gestione Contratti](#gestione-contratti)
- [Integrazione API](#integrazione-api)
- [Flussi di Lavoro e Automazioni](#flussi-di-lavoro-e-automazioni)
- [Dashboard e Amministrazione](#dashboard-e-amministrazione)
- [Test e Debug](#test-e-debug)
- [Sviluppo](#sviluppo)
- [Sicurezza](#sicurezza)
- [Contribuire](#contribuire)
- [Licenza](#licenza)

---

## Panoramica

S121 Pulse Manager è un plugin WordPress completo sviluppato da **Studio 121** per gestire servizi ricorrenti per clienti, contratti e cicli di fatturazione. Offre rinnovi automatici dei contratti, monitoraggio delle scadenze, promemoria via email e integrazione perfetta con la piattaforma di fatturazione Fatture in Cloud tramite OAuth2.

Il plugin è progettato per agenzie e fornitori di servizi che hanno bisogno di tracciare i contratti dei clienti, gestire la fatturazione ricorrente e mantenere sincronizzati i dati clienti tra WordPress e sistemi di contabilità esterni.

---

## Funzionalità

### Funzionalità Principali

- **Custom Post Types** [post-types/clienti.php:4-24, post-types/servizi.php:4-24, post-types/contratti.php:4-30]
  - `clienti` — Gestione anagrafica e profili clienti
  - `servizi` — Catalogo servizi e template  
  - `contratti` — Gestione ciclo di vita contratti

- **Gestione Avanzata Contratti** [includes/class-contract-handler.php:1-895]
  - Rinnovi automatici dei contratti con frequenze configurabili (mensile, trimestrale, semestrale, annuale)
  - Rilevamento intelligente delle scadenze e transizioni di stato
  - Tracciamento storico contratti con log dettagliato delle operazioni
  - Calcolo automatico prezzi da template servizi

- **Integrazione Fatture in Cloud** [api/fatture-in-cloud.php:1-181, includes/oauth-utils.php:1-53]
  - Flusso di autenticazione OAuth2 sicuro
  - Sincronizzazione completa dati clienti con deduplicazione
  - Normalizzazione automatica dati (email, partite IVA, indirizzi, numeri di telefono)
  - Rate limiting e logica retry per stabilità API

- **Sincronizzazione Intelligente** [api/fatture-in-cloud.php:42-181]
  - Previene duplicati usando mapping campo `id_fatture_in_cloud`
  - Aggiorna clienti esistenti automaticamente
  - Gestisce paginazione API per grandi dataset clienti
  - Gestione errori robusta con backoff esponenziale

### Automazione e Monitoraggio

- **Automazione Ciclo di Vita Contratti** [includes/class-contract-handler.php:465-580]
  - Controlli CRON giornalieri per scadenze contratti 
  - Aggiornamenti automatici stato (attivo → scaduto → cessato)
  - Auto-rinnovo configurabile con periodi di tolleranza
  - Sistema promemoria email con template personalizzabili

- **Dashboard e Analytics** [admin/dashboard-contratti.php:1-333]
  - Panoramica stato contratti in tempo reale
  - Tracciamento ricavi e calcolo ricavi ricorrenti mensili
  - Avvisi scadenze imminenti con indicatori di priorità
  - Pulsanti azione rapida per gestione contratti

- **Gestione Avanzata Campi** [acf-fields/]
  - Popolamento dinamico moduli da template servizi
  - Campi bloccati dopo creazione contratto per prevenire corruzione dati
  - Validazione server-side e enforcement campi
  - Miglioramenti UI personalizzati con integrazione JavaScript

---

## Architettura e Struttura del Progetto

### Architettura di Alto Livello

```
Plugin WordPress
├── Custom Post Types (Clienti, Servizi, Contratti)
├── ACF Field Groups & Validazione
├── Contract Handler (Gestione Stato)
├── Layer Integrazione API (Fatture in Cloud)
├── Sistema Automazione CRON
└── Dashboard Admin & UI
```

### Struttura del Progetto

```
s121-pulse-manager/
├── acf-fields/                    # Definizioni campi ACF
│   ├── acf-clienti.php           # Campi clienti [L:1-40]
│   ├── acf-servizi.php           # Campi template servizi [L:1-180]
│   └── acf-contratti.php         # Campi gestione contratti [L:1-285]
├── admin/                         # Interfaccia amministrazione
│   └── dashboard-contratti.php   # Dashboard contratti [L:1-333]
├── api/                          # Integrazioni esterne
│   └── fatture-in-cloud.php     # Client API FIC [L:1-181]
├── assets/                       # Risorse frontend
│   ├── css/admin.css             # Stili admin [L:1-5]
│   └── js/
│       ├── acf-dynamic-values.js # Comportamenti moduli dinamici [L:1-263]
│       └── smp-native-save.js    # Funzionalità salvataggio avanzate [L:1-145]
├── cron/                         # Task in background  
│   └── reminder-rinnovi.php      # Promemoria rinnovi [L:1-40]
├── includes/                     # Classi principali
│   ├── class-contract-handler.php # Ciclo vita contratti [L:1-895]
│   ├── class-date-helper.php     # Utility date [L:1-133]
│   ├── oauth-utils.php           # Gestione OAuth [L:1-53]
│   └── spm-list-inline-actions.php # Azioni lista admin [L:1-37]
├── post-types/                   # Definizioni CPT
│   ├── clienti.php               # CPT clienti [L:1-25]
│   ├── servizi.php               # CPT servizi [L:1-25]
│   └── contratti.php             # CPT contratti [L:1-30]
├── composer.json                 # Dipendenze PHP [L:1-5]
├── oauth.php                     # Utility setup OAuth [L:1-60]
└── s121-pulse-manager.php        # File plugin principale [L:1-105]
```

---

## Primi Passi

### Prerequisiti

- **WordPress**: Versione 5.8 o superiore
- **PHP**: Versione 7.4 o superiore
- **ACF Pro**: Plugin Advanced Custom Fields Pro
- **Composer**: Per gestione dipendenze
- **Account Fatture in Cloud**: Con accesso API abilitato

### Installazione

1. **Download e Installazione**
   ```bash
   # Clona o scarica nella cartella plugin WordPress
   cd wp-content/plugins/
   git clone [repository-url] s121-pulse-manager
   ```

2. **Installa Dipendenze**
   ```bash
   cd s121-pulse-manager
   composer install
   ```

3. **Attiva Plugin**
   - Vai su WordPress Admin → Plugin
   - Attiva "S121 Pulse Manager"

4. **Configura ACF Pro**
   - Assicurati che ACF Pro sia installato e attivato
   - I gruppi di campi saranno registrati automaticamente [acf-fields/]

### Avvio Rapido

1. **Setup OAuth** [oauth.php:1-60]
   ```
   # Visita la pagina di setup OAuth
   https://yoursite.com/wp-content/plugins/s121-pulse-manager/oauth.php
   ```

2. **Crea il Tuo Primo Servizio** [post-types/servizi.php]
   - Vai su WordPress Admin → Servizi → Aggiungi Nuovo
   - Imposta prezzo, frequenza fatturazione e durata contratto

3. **Aggiungi Clienti** [post-types/clienti.php]
   - Manualmente via WordPress Admin → Clienti
   - O sincronizza automaticamente da Fatture in Cloud

4. **Crea Contratti** [post-types/contratti.php]
   - WordPress Admin → Contratti → Aggiungi Nuovo
   - Seleziona cliente e servizio (compila automaticamente i default)
   - Imposta data attivazione (scadenza calcolata automaticamente)

---

## Configurazione

### Variabili d'Ambiente e Opzioni

| Chiave Opzione | Descrizione | Default | Fonte |
|----------------|-------------|---------|-------|
| `SPM_FIC_CLIENT_ID` / `spm_fic_client_id` | OAuth Client ID Fatture in Cloud | - | [includes/oauth-utils.php:12] |
| `SPM_FIC_CLIENT_SECRET` / `spm_fic_client_secret` | OAuth Client Secret Fatture in Cloud | - | [includes/oauth-utils.php:13] |
| `spm_fic_access_token` | Token OAuth accesso Fatture in Cloud | - | [oauth.php:44] |
| `spm_fic_refresh_token` | Token refresh OAuth per auto-rinnovo | - | [oauth.php:45] |
| `spm_fic_company_id` | ID azienda target in Fatture in Cloud | - | [oauth.php:51] |
| `spm_last_sync_timestamp` | Timestamp ultima sincronizzazione clienti | - | [api/fatture-in-cloud.php:162] |
| `spm_last_sync_method` | Trigger sincronizzazione (manuale/cron) | - | [api/fatture-in-cloud.php:163] |

Per sicurezza, definire `SPM_FIC_CLIENT_ID` e `SPM_FIC_CLIENT_SECRET` nel file `wp-config.php` o salvarli come opzioni protette `spm_fic_client_id` e `spm_fic_client_secret`. **Non** includere queste credenziali nel repository.

```php
// wp-config.php
define('SPM_FIC_CLIENT_ID', 'your-client-id');
define('SPM_FIC_CLIENT_SECRET', 'your-client-secret');
```

### Configurazione Servizi [acf-fields/acf-servizi.php:1-180]

**Impostazioni Base:**
- **Categoria**: Classificazione (hosting, manutenzione, social, advertising, altro)
- **Prezzo Base**: Prezzo predefinito per nuovi contratti  
- **Frequenza Fatturazione**: Quanto spesso vengono generate le fatture
- **Durata Contratto**: Lunghezza contratto standard
- **Auto-Rinnovo**: Comportamento rinnovo predefinito

**Opzioni Avanzate:**
- **Giorni Pre-Promemoria**: Tempo di anticipo per notifiche scadenza
- **Template Email**: Messaggio promemoria personalizzato con placeholder
- **Codice Prodotto FIC**: Mapping integrazione per fatturazione automatica

### Configurazione Contratti [acf-fields/acf-contratti.php:1-285]

**Impostazioni Principali:**
- **Cliente**: Link al record cliente (bloccato dopo creazione)
- **Servizio**: Link al template servizio (bloccato dopo creazione)  
- **Prezzo Contratto**: Sovrascrive prezzo base servizio
- **Frequenza Fatturazione**: Sovrascrive default servizio
- **Durata Contratto**: Sovrascrive default servizio
- **Stato**: attivo, sospeso, scaduto, cessato

**Campi Calcolati:**
- **Data Attivazione**: Data inizio contratto
- **Prossima Scadenza**: Auto-calcolata basata su durata [assets/js/acf-dynamic-values.js:45-65]

---

## Utilizzo

### Flusso di Lavoro Gestione Contratti

#### Creazione Contratti [includes/class-contract-handler.php:200-250]

1. **Creazione Nuovo Contratto**
   ```php
   // Popolamento automatico campi da template servizio
   // Data scadenza calcolata: data_attivazione + frequenza
   // Stato iniziale impostato su 'attivo'
   // Voce log storico: 'creazione'
   ```

2. **Transizioni Stato Contratto** [includes/class-contract-handler.php:270-310]
   ```
   attivo → scaduto (automatico alla data scadenza)
   scaduto → cessato (dopo periodo di tolleranza)
   attivo/scaduto → sospeso (manuale)
   sospeso → attivo/scaduto (manuale, basato su scadenza)
   ```

#### Azioni Manuali [includes/class-contract-handler.php:350-465]

**Processo di Rinnovo:**
- **Contratti Attivi**: Estende scadenza di un periodo
- **Recentemente Scaduti**: Rinnovo catch-up a data corrente + un periodo  
- **Molto Scaduti**: Auto-cessati (soglia configurabile: 90 giorni)

**Gestione Stato:**
- **Sospendi**: Pausa contratto (preserva data scadenza)
- **Riattiva**: Riprendi con stato appropriato basato su scadenza
- **Cessa**: Stato finale, previene ulteriori modifiche

### Operazioni Dashboard [admin/dashboard-contratti.php:1-333]

**Statistiche Rapide:**
- Conteggio contratti attivi e ricavi ricorrenti mensili
- Contratti in scadenza (prossimi 30 giorni) con indicatori priorità
- Contratti scaduti che richiedono attenzione
- Riepilogo attività rinnovi automatici

**Gestione Lista Contratti:**
- Colonne ordinabili: cliente, servizio, scadenza, stato
- Filtri avanzati: per servizio, range date, frequenza, stato
- Azioni inline: rinnova, sospendi, riattiva, cessa
- Supporto operazioni bulk

### Funzionalità di Automazione

#### Elaborazione CRON Giornaliera [includes/class-contract-handler.php:465-580]

1. **Controllo Scadenze**
   ```php
   // Aggiorna stati contratti basati su date scadenza
   // attivo → scaduto (alla scadenza)
   // scaduto → cessato (oltre tolleranza)
   ```

2. **Auto-Rinnovi**
   ```php
   // Elabora contratti con auto_renewal = true
   // Estende scadenza e aggiorna stato ad attivo
   // Logga rinnovo nello storico contratto
   ```

3. **Email Promemoria**
   ```php
   // Invia notifiche basate su giorni_pre_promemoria
   // Usa template servizio o messaggio predefinito
   // Traccia stato invio per prevenire duplicati
   ```

---

## Integrazione API

### Integrazione Fatture in Cloud [api/fatture-in-cloud.php:1-181]

#### Flusso di Autenticazione [oauth.php:1-60]

```php
// Flusso OAuth 2.0 Authorization Code
$oauth = new OAuth2AuthorizationCodeManager($clientId, $clientSecret, $redirectUri);

// Step 1: Reindirizza a URL autorizzazione
$url = $oauth->getAuthorizationUrl([Scope::ENTITY_CLIENTS_READ], "state");

// Step 2: Gestisci callback e scambia codice per token
$tokenObj = $oauth->fetchToken($_GET['code']);
```

#### Sincronizzazione Clienti [api/fatture-in-cloud.php:90-181]

**Processo di Sincronizzazione:**
1. **Recupera Tutti i Clienti**: Chiamate API paginate con logica retry
2. **Deduplicazione**: Match tramite campo `id_fatture_in_cloud`
3. **Normalizzazione Dati**: Standardizza email, partite IVA, indirizzi
4. **Integrazione WordPress**: Crea/aggiorna post clienti con campi ACF

**Gestione Errori:**
- Protezione rate limiting con backoff esponenziale
- Refresh automatico token alla scadenza [includes/oauth-utils.php:1-53]
- Logging completo per debugging e monitoraggio

**Trigger Sincronizzazione Manuale:**
```
https://yoursite.com/wp-admin/?spm_test_sync=1
```

#### Rate Limiting API [api/fatture-in-cloud.php:55-75]

```php
// Logica retry per richieste rate-limited
function smp_list_clients_with_retry($api, $company_id, $page, $per_page = 100, $max_attempts = 3) {
    // Implementa backoff esponenziale per errori 429 e 5xx
    // Fallimento immediato per errori client 4xx
}
```

---

## Flussi di Lavoro e Automazioni

### Elaborazione Automatica Contratti

#### Programma CRON [s121-pulse-manager.php:85-95]

- **Sincronizzazione Giornaliera**: Dati clienti da Fatture in Cloud (00:00)
- **Controllo Giornaliero**: Scadenze e rinnovi contratti (08:00)  
- **Promemoria Email**: Basati su impostazioni specifiche contratto

#### Eventi Ciclo Vita Contratti [includes/class-contract-handler.php:580-650]

```php
// Evento: Creazione Contratto
log_operazione($post_id, 'creazione', $importo, $nota, $contesto);

// Evento: Rinnovo Automatico  
log_operazione($post_id, 'rinnovo_automatico', $importo, $nota, $contesto);

// Evento: Azione Manuale
log_operazione($post_id, 'modifica', $importo, $nota, $contesto);

// Evento: Rilevamento Scadenza
log_operazione($post_id, 'scadenza', $importo, $nota, $contesto);
```

### Automazione Email [cron/reminder-rinnovi.php:1-40]

**Sistema Promemoria:**
- Tempo di anticipo configurabile per tipo servizio
- Messaggi basati su template con sostituzione variabili
- Tracciamento consegna per prevenire invii duplicati
- Integrazione con funzioni mail WordPress

**Variabili Template:**
- `{CLIENTE}`: Nome cliente
- `{SERVIZIO}`: Nome servizio  
- `{GIORNI}`: Giorni alla scadenza
- `{SCADENZA}`: Data scadenza formattata

---

## Dashboard e Amministrazione

### Dashboard Contratti [admin/dashboard-contratti.php:30-150]

**Panoramica Statistiche:**
- Distribuzione stati contratti (attivo, sospeso, scaduto, cessato)
- Calcoli ricavi ricorrenti mensili
- Conteggi contratti per tipo servizio
- Attività recenti e trend

**Gestione Scadenze:**
- Scadenze imminenti con codice priorità (rosso: <7 giorni, arancione: <15 giorni, verde: >15 giorni)
- Pulsanti azione rapida per operazioni comuni
- Link diretti a pagine modifica contratto

**Analytics Ricavi:**
- Trend ricavi storici (vista 6 mesi)
- Distribuzione valore contratti
- Metriche performance servizi
- Tracciamento tasso rinnovi

### Miglioramenti Lista Admin

#### Colonne Personalizzate [includes/class-contract-handler.php:650-720]

- **Cliente**: Nome cliente con link
- **Servizio**: Tipo servizio con link modifica
- **Scadenza**: Data con codice colore e indicatori urgenza  
- **Stato**: Badge stato visivi con icone
- **Azioni Rapide**: Pulsanti inline per rinnovo e cambio stato

#### Filtri Avanzati [includes/class-contract-handler.php:750-850]

- **Filtro Servizio**: Dropdown servizi disponibili
- **Range Date**: Date picker da/a per filtro scadenze
- **Filtro Stato**: Multi-select per stati contratti
- **Filtro Frequenza**: Filtro basato su durata contratto

#### Colonne Ordinabili [includes/class-contract-handler.php:720-750]

- Nome cliente (alfabetico)
- Tipo servizio (alfabetico)  
- Data scadenza (cronologico)
- Stato contratto (raggruppato)

---

## Test e Debug

### Strumenti di Debug

#### Test Sincronizzazione Manuale [s121-pulse-manager.php:75-80]

```
# Trigger sincronizzazione manuale clienti con output verboso
https://yoursite.com/wp-admin/?spm_test_sync=1
```

**Output Debug Include:**
- Riepiloghi risposta API
- Conteggi creazione/aggiornamento clienti
- Dettagli errori e tentativi risoluzione
- Informazioni timing performance

#### Validazione Token [test-token.php:1-10]

```php
# Testa validità token OAuth
$token = get_valid_token();
// Tenta automaticamente refresh se scaduto
// Ritorna false se refresh fallisce
```

### Logging Errori [api/fatture-in-cloud.php:155-165]

**Eventi Loggati:**
- Fallimenti autenticazione API
- Risultati operazioni sincronizzazione  
- Transizioni stato contratti
- Riepiloghi esecuzione CRON

**Posizioni Log:**
- WordPress debug.log (se WP_DEBUG_LOG abilitato)
- Storage opzioni database per timestamp sincronizzazione
- Storico contratti per cambiamenti operazionali

### Modalità Sviluppo

#### Debug ACF [acf-fields/acf-contratti.php:130-180]

```javascript
// Logging popolamento campi dinamici
console.log('✅ Dati servizio precompilati:', data);
console.log('📅 Frequenza FORZATA a:', frequenza);
```

#### Blocco Campi UI [includes/class-contract-handler.php:850-895]

- Indicatori visivi per campi bloccati
- Enforcement server-side per prevenire manomissioni
- Degradazione elegante per restrizioni permessi

---

## Sviluppo

### Punti di Estensione

#### Azioni Contratto Personalizzate

```php
// Registra nuova azione contratto
add_action('spm_contract_action_{nome_azione}', 'handler_personalizzato');

function handler_personalizzato($post_id) {
    // Logica personalizzata
    SPM_Contract_Handler::log_operazione($post_id, 'azione_personalizzata', $importo, $nota);
}
```

#### Hook Integrazione Servizi

```php
// Modifica default servizi prima della creazione contratto
add_filter('spm_service_defaults', function($defaults, $service_id) {
    // Logica personalizzata
    return $defaults;
}, 10, 2);
```

#### Personalizzazione Template Email

```php
// Sovrascrive contenuto email promemoria
add_filter('spm_reminder_email_content', function($content, $contract_id) {
    // Logica template personalizzata
    return $content;
}, 10, 2);
```

### Schema Database

#### Struttura Campi Personalizzati

**Clienti (clienti):**
- `email`: Indirizzo email cliente
- `partita_iva`: Partita IVA
- `telefono`: Numero di telefono  
- `id_fatture_in_cloud`: ID cliente FIC (chiave sincronizzazione)
- `note_fic`: Note da Fatture in Cloud

**Servizi (servizi):**
- `prezzo_base`: Prezzo servizio predefinito
- `frequenza_ricorrenza`: Durata contratto predefinita
- `giorni_pre_reminder`: Tempo anticipo promemoria predefinito
- `rinnovo_automatico_default`: Impostazione auto-rinnovo predefinita

**Contratti (contratti):**
- `cliente`: ID post cliente (ACF post_object)
- `servizio`: ID post servizio (ACF post_object)  
- `prezzo_contratto`: Override prezzo specifico contratto
- `frequenza`: Durata contratto
- `stato`: Stato corrente (attivo/sospeso/scaduto/cessato)
- `data_attivazione`: Data inizio contratto
- `data_prossima_scadenza`: Prossima data scadenza (auto-calcolata)
- `storico_contratto`: Storico operazioni (ACF repeater)

### Stile Codice e Standard

- **Standard Codifica WordPress**: Conforme PSR-12 dove applicabile
- **Sicurezza**: Verifica nonce, controlli capacità, sanificazione input
- **Performance**: Query efficienti, caching dove appropriato  
- **Manutenibilità**: Design modulare, documentazione completa

---

## Sicurezza

### Autenticazione e Autorizzazione

- **OAuth 2.0**: Autenticazione API sicura basata su token [includes/oauth-utils.php]
- **Capacità WordPress**: Controllo accesso basato su ruoli per tutte le operazioni
- **Verifica Nonce**: Protezione CSRF per tutte le operazioni AJAX [includes/class-contract-handler.php:675]

### Protezione Dati

- **Sanificazione Input**: Tutti gli input utente filtrati e validati
- **Prevenzione SQL Injection**: Prepared statement WordPress e WP_Query
- **Protezione XSS**: Escaping output con funzioni WordPress

### Sicurezza API [api/fatture-in-cloud.php:55-75]

- **Rate Limiting**: Backoff esponenziale per richieste API
- **Gestione Token**: Refresh automatico con storage sicuro
- **Gestione Errori**: Previene divulgazione informazioni nei messaggi errore

---

## Contribuire

### Flusso di Lavoro Sviluppo

1. **Fork Repository**: Crea fork personale per sviluppo
2. **Branch Funzionalità**: Usa nomi branch descrittivi (`feature/rinnovo-contratti`)
3. **Standard Codice**: Segui linee guida WordPress e PSR-12
4. **Test**: Verifica funzionalità in ambiente WordPress pulito
5. **Documentazione**: Aggiorna README e commenti inline

### Convenzioni Commit

```
feat: aggiungi funzionalità rinnovo bulk contratti
fix: risolvi caso edge calcolo data scadenza  
docs: aggiorna esempi integrazione API
style: migliora layout responsive dashboard admin
refactor: ottimizza performance sincronizzazione clienti
```

### Segnalazione Problemi

**I Report Bug Dovrebbero Includere:**
- Versione WordPress e dettagli ambiente
- Versione plugin e configurazione
- Passi per riprodurre il problema
- Comportamento atteso vs effettivo
- Log errori pertinenti

**Le Richieste Funzionalità Dovrebbero Includere:**
- Descrizione caso d'uso e valore business
- Soluzione proposta o approccio implementazione
- Considerazioni compatibilità
- Priorità e requisiti timeline

---

## Licenza

Questo plugin è distribuito sotto la **GNU General Public License v2 o successive**.

```
S121 Pulse Manager - Plugin WordPress
Copyright (C) 2024 Studio 121

Questo programma è software libero; puoi ridistribuirlo e/o modificarlo
secondo i termini della GNU General Public License come pubblicata dalla
Free Software Foundation; sia la versione 2 della Licenza, o
(a tua scelta) qualsiasi versione successiva.

Questo programma è distribuito nella speranza che sia utile,
ma SENZA ALCUNA GARANZIA; senza nemmeno la garanzia implicita di
COMMERCIABILITÀ o IDONEITÀ PER UN PARTICOLARE SCOPO. Vedi la
GNU General Public License per maggiori dettagli.
```

---

## Supporto e Contatti

**Studio 121**  
Sito Web: [https://studio121.it](https://studio121.it)  
Email: info@studio121.it

**Documentazione**: Documentazione plugin ed esempi disponibili nel repository  
**Problemi**: Segnala bug e richiedi funzionalità tramite issues repository  
**Aggiornamenti**: Aggiornamenti plugin distribuiti tramite admin WordPress o download manuale

---

*Ultimo Aggiornamento: Agosto 2025 | Versione 2.0.0*