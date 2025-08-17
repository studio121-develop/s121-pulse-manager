#!/usr/bin/env bash
set -euo pipefail

# Esegui questo script DENTRO wp-content/plugins/s121-pulse-manager
# Crea un backup .bak per ogni file che modifica

shopt -s globstar nullglob

changed=0
for f in **/*.php; do
  # Salta file di backup o vendor, se vuoi
  [[ "$f" == *.bak ]] && continue

  orig_sum="$(sha1sum "$f" | awk '{print $1}')"
  tmp="$(mktemp)"
  cp -- "$f" "$tmp"

  # 1) Rimuovi BOM UTF-8 se presente
  # 2) Rimuovi righe/spazi PRIMA di `<?php` (solo in testa al file)
  # 3) Rimuovi eventualmente `?>` finale + whitespace in coda
  awk '
	BEGIN { bom_removed=0; started=0 }
	NR==1 {
	  # Rimuovi BOM se presente
	  if (substr($0,1,3) == "\xEF\xBB\xBF") {
		$0 = substr($0,4)
		bom_removed=1
	  }
	}
	# Salta righe vuote/spazi finché non incontriamo l opening tag
	started==0 {
	  if ($0 ~ /^[[:space:]]*<\?php/) { started=1; print; next }
	  if ($0 ~ /^[[:space:]]*$/) { next }        # riga vuota → skip
	  if ($0 ~ /^[[:space:]]*<\?=/) {            # short echo non ammesso in testa
		started=1; print "<?php"; print substr($0, index($0, "<?=")+3); next
	  }
	  # Qualsiasi output prima di <?php è illegale -> lo droppiamo
	  next
	}
	{ print }
  ' "$tmp" > "$tmp.1"

  # Rimuovi closing tag finale + whitespace
  perl -0777 -pe '
	s/\s+\z//;                         # trim finale
	s/\?>\s*\z//;                      # togli closing tag in fondo
  ' "$tmp.1" > "$tmp.2"

  # Se è cambiato, salva backup e sovrascrivi
  new_sum="$(sha1sum "$tmp.2" | awk '{print $1}')"
  if [[ "$new_sum" != "$orig_sum" ]]; then
	cp -- "$f" "$f.bak"
	mv -- "$tmp.2" "$f"
	echo "Fix: $f"
	((changed++))
  fi

  rm -f -- "$tmp" "$tmp.1" "$tmp.2" 2>/dev/null || true
done

echo "Completato. File modificati: $changed"
echo "Backup .bak creati per i file toccati."
