# 📊 S121 Pulse Manager

**S121 Pulse Manager** è un plugin WordPress sviluppato da **Studio 121** per gestire servizi ricorrenti, clienti, reminder automatici e integrazione diretta con **Fatture in Cloud**.

---

## 🚀 Funzionalità principali

- ✅ **Custom Post Types**:
  - `clienti` — Anagrafica clienti
  - `servizi` — Servizi disponibili
  - `servizi_clienti` — Mappatura cliente → servizio

- 🧩 **Integrazione Fatture in Cloud (OAuth2)**:
  - Connessione sicura con API Fatture in Cloud
  - Sincronizzazione completa dell’anagrafica clienti (con normalizzazione dei dati)

- 🔁 **Sincronizzazione intelligente**:
  - Evita duplicati grazie al campo `id_fatture_in_cloud`
  - Aggiorna automaticamente clienti già esistenti
  - Normalizza email, P.IVA, CF, indirizzi e numeri di telefono

- ⏰ **Reminder servizi**:
  - Sistema CRON per controllare scadenze e rinnovi
  - Reminder email automatici (funzione modulabile)

- 🧪 **Modalità Debug**:
  - Output visivo per ogni sincronizzazione
  - Diagnostica semplice via browser (`?spm_test_sync=1`)

- ⚙️ **Pannello di Controllo WP**:
  - Dashboard moderna e informativa
  - Pulsante per sincronizzazione manuale clienti
  - Visualizzazione ultima sincronizzazione (data + metodo)

---

## 🛠️ Come si usa

### 1. **Installazione**

- Copia il plugin nella directory `wp-content/plugins/s121-pulse-manager`
- Attiva il plugin da **WordPress > Plugin**

### 2. **Configurazione**

- Inserisci l’`access token` OAuth2 (generato tramite procedura guidata)
- Salva l'`ID azienda` nelle opzioni (`spm_fic_company_id`)

### 3. **Sincronizzazione**

- Manuale via:
  - `https://yoursite.com/wp-admin/?spm_test_sync=1`
  - **Oppure dal pulsante nella Dashboard del plugin**
- Automatica ogni giorno alle **00:00** (CRON WordPress)

---

## 📂 Struttura dei File

```
s121-pulse-manager/
├── acf-fields/
│   ├── acf-clienti.php
│   ├── acf-servizi.php
│   └── acf-servizi-clienti.php
├── admin/
│   ├── rinnovo-manuale.php
│   └── reset-reminder.php
├── api/
│   └── fatture-in-cloud.php
├── cron/
│   └── reminder-rinnovi.php
├── includes/
│   └── oauth-utils.php
├── post-types/
│   ├── clienti.php
│   ├── servizi.php
│   └── servizi-clienti.php
└── s121-pulse-manager.php
```

---

## 🔄 Debug e Comandi Utili

- **Visualizzare clienti da API**:  
  `https://yoursite.com/wp-admin/?spm_test_visualizza=1`

- **Ultima sincronizzazione (salvata in opzioni WP)**:
  - `spm_last_sync_timestamp`
  - `spm_last_sync_method` (`manuale` o `cron`)

---

## 📌 Requisiti

- WordPress >= 5.8
- PHP >= 7.4
- Plugin [ACF Pro](https://www.advancedcustomfields.com/pro/)
- Token Fatture in Cloud con permessi `entities.clients:read`

---

## 🧑‍💻 Sviluppato da

**Studio 121**  
[https://studio121.it](https://studio121.it)  
Contatti: info@studio121.it

---

## 📃 Licenza

Questo plugin è distribuito sotto licenza **GPL v2 o superiore**.
