Spendenquittungs-Automat
========================

Generiert Spendenquittungen automatisch aus den GnuCash-Daten.


Schritt 1: gnucash_spendenexport.php
------------------------------------

Liest die Daten aus gnucash, filtert nach diversen Kriterien (Konto, Datumsbereich), schreibt die
Buchungen als .json-Files in ./spendenquittung


Schritt 2: json2tex2pdf.php
---------------------------

Liest die JSON-Files in ./spendenquittung und die .tex-Vorlage in ./vorlage. Danach werden die
Platzhalter in der Vorlage für jeden einzelnen Spender ersetzt und as .tex in ./spendenquittung
gespeichert. Zum schluss werden die .tex-Files in PDF konvertiert.

TODO
- Die Adresse wird noch nicht ausgelesen. Momentan ersetze ich nur Betrag und Datum.
