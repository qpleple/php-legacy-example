Spécifications fonctionnelles et techniques

Application de comptabilité simplifiée (style 2006)

Version: 1.0

⸻

1. Objectif

Développer une application web interne de comptabilité simplifiée permettant:
	•	la tenue de journaux comptables (Ventes, Achats, Banque, OD),
	•	la saisie et validation d’écritures en partie double,
	•	le pointage bancaire via import CSV,
	•	le lettrage manuel clients/fournisseurs,
	•	la production d’états (grand livre, balance, journaux, synthèse TVA),
	•	l’export PDF des états et des pièces,
	•	la clôture de périodes et la clôture annuelle avec génération d’à-nouveaux.

L’application doit être volontairement conçue et implémentée comme en 2006:
	•	PHP procédural sans framework,
	•	pages PHP mélangeant HTML et appels PHP,
	•	SQL inline dans les pages,
	•	jQuery pour interactions simples,
	•	sessions PHP pour gestion utilisateur et états UI,
	•	absence de couche métier propre, logique éparpillée dans les pages,
	•	utilisation de MySQL avec tables simples, contraintes minimales, pas de migrations structurées.

⸻

2. Périmètre et exclusions

Inclus:
	•	Une seule société (mono-tenant).
	•	Un exercice courant actif et éventuellement un exercice suivant pour les à-nouveaux.
	•	TVA simple (2 à 3 taux).
	•	Import CSV relevé bancaire.
	•	Import CSV écritures (format imposé).
	•	Génération PDF.

Exclus:
	•	Déclarations fiscales officielles (FEC, liasse, etc.).
	•	Multi-devises.
	•	Gestion de stock, gestion commerciale avancée.
	•	OCR, scan intelligent.
	•	Workflow de validation multi-niveaux.
	•	API.

⸻

3. Utilisateurs et rôles

3.1 Types d’utilisateurs
	•	Admin: paramétrage, gestion utilisateurs, clôture.
	•	Comptable: saisie, validation, états, lettrage, pointage.
	•	Lecture: accès lecture aux états uniquement.

3.2 Authentification
	•	Page de login classique.
	•	Session PHP stockant user_id, role, csrf_token.
	•	Mot de passe hashé en MD5 salé (style legacy volontaire) ou SHA1 (accepté). Pas de bcrypt.

⸻

4. Architecture technique imposée (style 2006)

4.1 Stack
	•	PHP 5.1.x (2006) en mode mod_php Apache.
	•	MySQL 5.0.x.
	•	jQuery 1.2.x (ou proche, disponible).
	•	CSS simple, pas de build.
	•	Librairie PDF: FPDF (ou TCPDF ancien) intégrée dans /lib/.
	•	Docker Compose fourni pour exécuter l’ensemble, mais l’environnement doit émuler au maximum 2006.

4.2 Structure du projet (répertoire)
	•	/www/
	•	index.php (tableau de bord)
	•	login.php, logout.php
	•	header.php, footer.php (includes)
	•	/modules/
	•	/setup/ (paramétrage)
	•	/entries/ (écritures)
	•	/bank/ (banque)
	•	/letters/ (lettrage)
	•	/reports/ (états)
	•	/close/ (clôture)
	•	/admin/ (utilisateurs)
	•	/assets/
	•	/js/jquery.js
	•	/js/app.js
	•	/css/style.css
	•	/uploads/ (pièces justificatives)
	•	/pdf/ (génération à la volée ou stockage)
	•	/lib/
	•	db.php (connexion MySQL)
	•	auth.php (fonctions auth)
	•	utils.php
	•	pdf/ (FPDF)
	•	/sql/
	•	schema.sql (création tables)
	•	seed.sql (données initiales)

4.3 Conventions d’implémentation (volontairement legacy)
	•	Pages PHP procédurales:
	•	traitement POST en haut de fichier,
	•	puis rendu HTML.
	•	Accès DB via mysql_connect, mysql_query, mysql_fetch_assoc.
	•	Pas d’ORM, pas de classes.
	•	Validation côté serveur rudimentaire, messages stockés en $_SESSION['flash'].
	•	CSRF token simple:
	•	généré au login,
	•	champ caché _csrf,
	•	vérification dans chaque POST.
	•	Gestion des erreurs:
	•	die(mysql_error()) en dev,
	•	en prod: redirection avec message générique.
	•	Encodage:
	•	base en latin1 ou utf8 (choix libre), mais uniforme.
	•	Sécurité volontairement faible mais fonctionnelle:
	•	pas de prepared statements,
	•	échappement via mysql_real_escape_string.

4.4 Interactivité jQuery
	•	Ajout/suppression de lignes d’écriture sur la page.
	•	Auto-complétion simple via requête AJAX PHP (optionnel).
	•	Filtrage client-side basique de tableaux.
	•	Confirmations via confirm().

⸻

5. Modèle de données (MySQL)

5.1 Tables

users
	•	id INT PK AI
	•	username VARCHAR(50) UNIQUE
	•	password_hash VARCHAR(64)
	•	role ENUM(‘admin’,‘accountant’,‘viewer’)
	•	created_at DATETIME

company
	•	id INT PK (toujours 1)
	•	name VARCHAR(255)
	•	currency CHAR(3)
	•	fiscal_year_start DATE
	•	fiscal_year_end DATE

periods
	•	id INT PK AI
	•	start_date DATE
	•	end_date DATE
	•	status ENUM(‘open’,‘locked’) DEFAULT ‘open’
	•	Index start_date, end_date

accounts
	•	id INT PK AI
	•	code VARCHAR(20) UNIQUE
	•	label VARCHAR(255)
	•	type ENUM(‘general’,‘customer’,‘vendor’) DEFAULT ‘general’
	•	vat_default_rate_id INT NULL
	•	is_active TINYINT DEFAULT 1

third_parties
	•	id INT PK AI
	•	type ENUM(‘customer’,‘vendor’)
	•	name VARCHAR(255)
	•	account_id INT (FK logique vers accounts, pas de contrainte obligatoire)
	•	email VARCHAR(255) NULL
	•	created_at DATETIME

journals
	•	id INT PK AI
	•	code VARCHAR(10) UNIQUE (VE, AC, BK, OD)
	•	label VARCHAR(255)
	•	sequence_prefix VARCHAR(10)
	•	next_number INT DEFAULT 1
	•	is_active TINYINT DEFAULT 1

vat_rates
	•	id INT PK AI
	•	label VARCHAR(50) (ex: TVA20)
	•	rate DECIMAL(5,2) (ex: 20.00)
	•	account_collected VARCHAR(20) (code compte)
	•	account_deductible VARCHAR(20) (code compte)
	•	is_active TINYINT DEFAULT 1

entries (pièces comptables)
	•	id INT PK AI
	•	journal_id INT
	•	entry_date DATE
	•	period_id INT
	•	piece_number VARCHAR(50) (séquence journal)
	•	label VARCHAR(255)
	•	status ENUM(‘draft’,‘posted’) DEFAULT ‘draft’
	•	total_debit DECIMAL(12,2)
	•	total_credit DECIMAL(12,2)
	•	created_by INT
	•	created_at DATETIME
	•	posted_at DATETIME NULL
	•	Index sur journal_id, entry_date, piece_number

entry_lines
	•	id INT PK AI
	•	entry_id INT
	•	line_no INT
	•	account_id INT
	•	third_party_id INT NULL
	•	label VARCHAR(255)
	•	debit DECIMAL(12,2) DEFAULT 0
	•	credit DECIMAL(12,2) DEFAULT 0
	•	vat_rate_id INT NULL
	•	vat_base DECIMAL(12,2) NULL
	•	vat_amount DECIMAL(12,2) NULL
	•	due_date DATE NULL
	•	Index entry_id, account_id, third_party_id

attachments
	•	id INT PK AI
	•	entry_id INT
	•	filename VARCHAR(255)
	•	stored_path VARCHAR(255)
	•	uploaded_at DATETIME

bank_accounts
	•	id INT PK AI
	•	label VARCHAR(255)
	•	account_id INT (compte 512xxx)
	•	is_active TINYINT DEFAULT 1

bank_statements
	•	id INT PK AI
	•	bank_account_id INT
	•	imported_at DATETIME
	•	source_filename VARCHAR(255)

bank_statement_lines
	•	id INT PK AI
	•	statement_id INT
	•	line_date DATE
	•	label VARCHAR(255)
	•	amount DECIMAL(12,2) (positif = crédit banque, négatif = débit banque ou inverse selon convention fixée)
	•	ref VARCHAR(255) NULL
	•	matched_entry_line_id INT NULL
	•	status ENUM(‘unmatched’,‘matched’) DEFAULT ‘unmatched’
	•	Index statement_id, status

lettering_groups
	•	id INT PK AI
	•	account_id INT (compte tiers)
	•	third_party_id INT NULL
	•	created_at DATETIME
	•	created_by INT

lettering_items
	•	id INT PK AI
	•	group_id INT
	•	entry_line_id INT
	•	amount DECIMAL(12,2)

audit_log
	•	id INT PK AI
	•	user_id INT
	•	action VARCHAR(50)
	•	entity VARCHAR(50)
	•	entity_id INT
	•	details TEXT
	•	created_at DATETIME

5.2 Contraintes volontairement minimales
	•	Peu ou pas de foreign keys.
	•	Contrôles applicatifs en PHP.
	•	Index ciblés uniquement sur recherches listées.

⸻

6. Règles comptables et validations

6.1 Partie double
	•	Pour une pièce: somme des débits = somme des crédits.
	•	Seuil de tolérance d’arrondi: 0.01.
	•	Une ligne doit avoir soit débit soit crédit, pas les deux (sauf 0).

6.2 Statuts
	•	draft: modifiable.
	•	posted: non modifiable (sauf attachements et ajout de note dans audit).
	•	Toute suppression d’une pièce posted interdite.

6.3 Périodes
	•	Période open: saisie autorisée.
	•	Période locked: saisie et validation interdites.
	•	La période d’une pièce est déterminée à l’enregistrement selon entry_date.

6.4 Numérotation des pièces
	•	À la validation (posted) seulement:
	•	récupérer journals.next_number,
	•	construire piece_number = prefix + YYYY + '-' + zero_pad(next_number, 6),
	•	incrémenter next_number.
	•	Risque de concurrence accepté (legacy). Implémentation:
	•	SELECT next_number FROM journals WHERE id=...
	•	puis UPDATE journals SET next_number = next_number + 1 WHERE id=...
	•	sans transaction stricte.

6.5 TVA (simple)
	•	Sur une ligne avec vat_rate_id:
	•	vat_base = montant HT de la ligne (debit ou credit selon sens).
	•	vat_amount = ROUND(vat_base * rate / 100, 2).
	•	La ligne TVA comptable doit être générée manuellement ou via bouton “Ajouter TVA” qui ajoute une ligne automatique:
	•	TVA collectée pour ventes (crédit),
	•	TVA déductible pour achats (débit),
	•	compte déterminé par vat_rates.account_*.
	•	La synthèse TVA somme les vat_amount des lignes taguées TVA, par période.

⸻

7. Écrans et fonctionnalités

7.1 Navigation

Menu latéral:
	•	Tableau de bord
	•	Paramétrage
	•	Écritures
	•	Banque
	•	Lettrage
	•	États
	•	Clôture
	•	Admin (si admin)

Chaque page inclut header.php et footer.php.

⸻

7.2 Module Paramétrage (/modules/setup/)

7.2.1 Société

Page: company.php
	•	Formulaire: nom, devise, dates exercice.
	•	Sauvegarde directe table company.
	•	Si modification dates exercice:
	•	avertissement UI,
	•	pas de recalcul automatique des périodes, admin doit régénérer.

7.2.2 Périodes

Page: periods.php
	•	Bouton “Générer périodes mensuelles”:
	•	supprime et recrée periods sur la base de l’exercice.
	•	Liste périodes avec statut.
	•	Bouton “Verrouiller”/“Déverrouiller” (admin seulement).

7.2.3 Plan comptable

Page: accounts.php
	•	CRUD simple comptes:
	•	code, libellé, type, actif.
	•	Recherche par code/libellé.
	•	Import CSV plan comptable (option):
	•	colonnes: code;libellé;type;actif.

7.2.4 Journaux

Page: journals.php
	•	CRUD journaux:
	•	code, libellé, prefix, next_number.
	•	Journaux par défaut: VE, AC, BK, OD.

7.2.5 Tiers

Page: third_parties.php
	•	CRUD tiers:
	•	type, nom, email, compte associé.
	•	Création automatique du compte tiers (option):
	•	pour customer: créer compte 411xxxx,
	•	pour vendor: créer compte 401xxxx,
	•	logique simple: max + 1 en numérique.

7.2.6 TVA

Page: vat.php
	•	CRUD taux TVA:
	•	label, rate, comptes collectée/déductible.
	•	Activer/désactiver un taux.

⸻

7.3 Module Écritures (/modules/entries/)

7.3.1 Liste des pièces

Page: list.php
Filtres:
	•	journal, période, statut, recherche texte (libellé, numéro).
Actions:
	•	Créer une pièce.
	•	Ouvrir une pièce.
	•	Imprimer PDF de la pièce.
	•	Dupliquer une pièce (crée draft identique).

7.3.2 Création/édition d’une pièce

Page: edit.php?id=... ou edit.php (nouveau)
Comportement:
	•	Si nouveau: création d’un entries en draft à la première sauvegarde.
	•	Champs:
	•	date, journal, libellé.
	•	Tableau lignes:
	•	compte (select), tiers (select si compte tiers), libellé, débit, crédit, TVA (select), échéance.
	•	jQuery:
	•	bouton “Ajouter ligne” clone une ligne vide,
	•	bouton “Supprimer ligne” retire la ligne côté client,
	•	calcul instantané total débit/crédit en bas de tableau,
	•	mise en rouge si déséquilibre.
	•	Sauvegarde:
	•	POST vers la même page,
	•	suppression puis réinsertion de toutes les lignes (pattern legacy):
	•	DELETE FROM entry_lines WHERE entry_id=...
	•	puis INSERT de chaque ligne.
	•	Pièces justificatives:
	•	upload fichier (pdf/jpg/png) vers /uploads/ avec renommage entry_{id}_timestamp.ext,
	•	insertion en table attachments.

7.3.3 Validation (passage en posted)

Action: bouton “Valider”
Règles:
	•	période doit être open,
	•	total_debit = total_credit,
	•	aucune ligne vide,
	•	numérotation générée,
	•	status devient posted,
	•	posted_at rempli.
Effets:
	•	écriture dans audit_log action POST_ENTRY.
	•	Après validation: page en lecture seule.

7.3.4 Import CSV écritures

Page: import.php
	•	Upload CSV.
	•	Format imposé (séparateur ;):
	•	date;journal_code;label;account_code;third_party_name;debit;credit;vat_rate_label;due_date;piece_ref
	•	Comportement:
	•	regrouper par piece_ref pour former des pièces distinctes,
	•	créer pièces en draft,
	•	option “Valider automatiquement” si équilibrées et périodes ouvertes.
	•	Rapport d’import:
	•	nombre pièces, erreurs par ligne.

⸻

7.4 Module Banque (/modules/bank/)

7.4.1 Comptes bancaires

Page: accounts.php
	•	CRUD: label, compte comptable (512xxx).

7.4.2 Import relevé bancaire

Page: import.php
	•	Sélection compte bancaire.
	•	Upload CSV (séparateur ;):
	•	date;label;amount;ref
	•	Création bank_statements + lignes.
	•	Normalisation legacy:
	•	trim, remplacer virgule par point dans amount,
	•	date format DD/MM/YYYY accepté.

7.4.3 Pointage (rapprochement léger)

Page: reconcile.php?statement_id=...
	•	Affiche lignes relevé unmatched.
	•	Affiche en dessous une recherche d’écritures Banque:
	•	requête sur entry_lines dont compte = compte bancaire sélectionné,
	•	statut entry posted,
	•	pas déjà appariées.
	•	Appariement:
	•	bouton “Matcher” sur une ligne relevé:
	•	associer matched_entry_line_id,
	•	passer statut matched.
	•	Écart:
	•	calcul somme relevé vs somme pièces matchées,
	•	affichage.

⸻

7.5 Module Lettrage (/modules/letters/)

7.5.1 Sélection compte/tiers

Page: select.php
	•	Choisir type (clients/fournisseurs),
	•	Choisir tiers,
	•	Ou choisir compte tiers.

7.5.2 Écran de lettrage

Page: letter.php?third_party_id=...
	•	Liste des lignes comptables (entry_lines) sur compte tiers:
	•	seulement pièces posted,
	•	tri par date.
	•	Colonnes: date, pièce, libellé, débit, crédit, solde cumulé, statut lettré.
	•	Lettrage manuel:
	•	cases à cocher sur lignes,
	•	champ “Montant à lettrer” optionnel (sinon total).
	•	Création d’un lettering_group et lettering_items.
	•	Règles:
	•	somme des montants sélectionnés doit être 0 (débit/crédit) avec tolérance 0.01.
	•	Affichage “reste à payer/encaisser”:
	•	calcul sur lignes non lettrées.

⸻

7.6 Module États (/modules/reports/)

7.6.1 Grand livre

Page: ledger.php
Filtres:
	•	période, compte (ou plage de comptes), journal optionnel.
Affichage:
	•	lignes: date, pièce, journal, libellé, débit, crédit, solde progressif.
Export:
	•	bouton PDF.

7.6.2 Balance

Page: trial_balance.php
Filtres:
	•	période.
Affichage par compte:
	•	total débit, total crédit, solde.
Export PDF.

7.6.3 Journal

Page: journal.php
Filtres:
	•	journal, période.
Affichage:
	•	pièces avec lignes groupées.
Export PDF.

7.6.4 Synthèse TVA

Page: vat_summary.php
Filtres:
	•	période.
Affichage:
	•	par taux: base, TVA collectée, TVA déductible,
	•	total et solde.
Export PDF.

Implémentation états (legacy):
	•	SQL agrégés directement dans la page.
	•	Option “Inclure brouillons” non disponible.

⸻

7.7 Module PDF (/modules/reports/pdf_*.php et /modules/entries/pdf.php)

7.7.1 Génération
	•	Utiliser FPDF.
	•	Chaque export appelle un script PHP qui:
	•	requête DB,
	•	construit PDF (A4 portrait),
	•	envoie headers Content-Type: application/pdf,
	•	propose téléchargement Content-Disposition: inline; filename=....

7.7.2 Templates
	•	Pas de moteur de template.
	•	Fonctions utilitaires dans /lib/pdf_helpers.php (facultatif) mais procédural.

7.7.3 PDFs requis
	•	Pièce comptable (une pièce):
	•	en-tête société, journal, date, numéro, libellé,
	•	tableau des lignes,
	•	totaux.
	•	Journal (mois/période).
	•	Grand livre (compte/période).
	•	Balance (période).
	•	TVA (période).

⸻

7.8 Module Clôture (/modules/close/)

7.8.1 Verrouillage période

Page: lock_period.php
	•	Admin choisit une période et clique “Verrouiller”.
	•	Effet: periods.status='locked'.
	•	Contrôle: pas de pièces draft dans période, sinon refus.

7.8.2 Clôture exercice et à-nouveaux

Page: year_end.php
Préconditions:
	•	toutes périodes de l’exercice verrouillées,
	•	aucune pièce draft dans exercice.
Actions:
	•	“Générer à-nouveaux”:
	•	calculer solde par compte à la fin de l’exercice:
	•	SUM(debit-credit) sur toutes les lignes posted.
	•	créer une pièce OD datée du premier jour du nouvel exercice:
	•	une ligne par compte non nul:
	•	si solde débiteur: débit,
	•	si solde créditeur: crédit.
	•	équilibrer via un compte “Report à nouveau” configurable (compte 110000 par défaut) si nécessaire.
	•	Marquer exercice clôturé:
	•	champ dans company ou table settings:
	•	fiscal_year_closed = 1.
	•	Déverrouiller les périodes du nouvel exercice (générées) manuellement.

Implémentation legacy:
	•	Pas de transactions globales, mais exécuter dans un ordre strict.
	•	Écrire un audit_log pour la clôture.

⸻

7.9 Module Admin (/modules/admin/)

7.9.1 Gestion utilisateurs

Page: users.php
	•	CRUD utilisateurs.
	•	Reset mot de passe (admin saisit un nouveau).
	•	Interdiction de supprimer son propre compte.

⸻

8. Comportements transverses

8.1 Autorisations

Fonction dans /lib/auth.php:
	•	require_login()
	•	require_role($role) avec hiérarchie admin > accountant > viewer.
Chaque page appelle ces fonctions en haut.

8.2 Flash messages
	•	$_SESSION['flash'] = array('type'=>'success','msg'=>'...')
	•	affichage dans header.php, puis unset.

8.3 Audit

Écrire dans audit_log pour:
	•	login/logout,
	•	création/modif/suppression (draft) d’une pièce,
	•	validation pièce,
	•	import CSV écritures,
	•	import relevé bancaire,
	•	matching banque,
	•	création/suppression lettrage,
	•	verrouillage période,
	•	clôture annuelle.

details contient un texte libre JSON-like (pas besoin d’être strict).

8.4 Performance et pagination
	•	Listes paginées à la main:
	•	paramètres page et limit,
	•	LIMIT offset, limit.
	•	Recherche via LIKE '%term%' directement.

⸻

9. Implémentations spécifiques attendues (legacy)

9.1 Connexion DB

Fichier /lib/db.php:
	•	variables globales $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME.
	•	mysql_connect, mysql_select_db.
	•	mysql_query("SET NAMES utf8") si utf8.

9.2 Requêtes SQL inline
	•	Les pages exécutent directement les SELECT/INSERT/UPDATE/DELETE.
	•	Les valeurs POST sont échappées à la main:
	•	$v = mysql_real_escape_string($_POST['field']);

9.3 Gestion formulaires
	•	Un seul endpoint par écran.
	•	Pattern:
	•	if ($_SERVER['REQUEST_METHOD']=='POST') { ... }
	•	puis HTML.
	•	Redirections après POST via header("Location: ..."); exit;

9.4 jQuery
	•	Fichier /assets/js/app.js:
	•	handler ajout ligne,
	•	recalcul totaux,
	•	masquage/affichage champs tiers selon type compte (si implémenté),
	•	appels AJAX simples vers ajax_accounts.php, ajax_third_parties.php si auto-complétion.

9.5 Upload fichiers
	•	Vérifier extension et taille (max 5MB).
	•	Stocker dans /www/uploads/.
	•	Aucune protection avancée, mais empêcher .php upload.

⸻

10. Docker Compose (exécution)

10.1 Services
	•	web:
	•	Apache + PHP 5.1.x
	•	volume monté sur /var/www/html
	•	db:
	•	MySQL 5.0.x
	•	volume persistant /var/lib/mysql

10.2 Initialisation DB
	•	À la première montée:
	•	exécuter /sql/schema.sql puis /sql/seed.sql.
	•	seed.sql crée:
	•	utilisateur admin,
	•	journaux par défaut,
	•	plan comptable minimal (401, 411, 512, 606, 707, 44566, 44571, 110, 120…),
	•	TVA 20% et 10% (exemple),
	•	périodes mensuelles de l’exercice.

⸻

11. Jeux de données et scénarios de démonstration

11.1 Scénario “Vente”
	•	Saisie d’une facture vente:
	•	411 Client (débit TTC),
	•	707 Ventes (crédit HT),
	•	44571 TVA collectée (crédit TVA).
	•	Validation.
	•	Lettrage avec règlement:
	•	512 Banque (débit),
	•	411 Client (crédit),
	•	lettrage des deux lignes 411.

11.2 Scénario “Achat”
	•	Facture fournisseur:
	•	606 Achats (débit HT),
	•	44566 TVA déductible (débit TVA),
	•	401 Fournisseur (crédit TTC).
	•	Règlement:
	•	401 (débit),
	•	512 (crédit),
	•	lettrage.

11.3 Scénario “Banque”
	•	Import relevé CSV incluant le règlement client et fournisseur.
	•	Matching manuel sur les écritures banque.

11.4 Scénario “Clôture”
	•	Verrouiller périodes.
	•	Générer à-nouveaux OD du nouvel exercice.
	•	Vérifier balance d’ouverture.

⸻

12. Liste exhaustive des pages (routes)
	•	/login.php, /logout.php
	•	/index.php

Setup:
	•	/modules/setup/company.php
	•	/modules/setup/periods.php
	•	/modules/setup/accounts.php
	•	/modules/setup/journals.php
	•	/modules/setup/third_parties.php
	•	/modules/setup/vat.php

Entries:
	•	/modules/entries/list.php
	•	/modules/entries/edit.php
	•	/modules/entries/import.php
	•	/modules/entries/pdf.php

Bank:
	•	/modules/bank/accounts.php
	•	/modules/bank/import.php
	•	/modules/bank/reconcile.php

Lettering:
	•	/modules/letters/select.php
	•	/modules/letters/letter.php

Reports:
	•	/modules/reports/ledger.php
	•	/modules/reports/trial_balance.php
	•	/modules/reports/journal.php
	•	/modules/reports/vat_summary.php
	•	/modules/reports/pdf_ledger.php
	•	/modules/reports/pdf_trial_balance.php
	•	/modules/reports/pdf_journal.php
	•	/modules/reports/pdf_vat.php

Close:
	•	/modules/close/lock_period.php
	•	/modules/close/year_end.php

Admin:
	•	/modules/admin/users.php

AJAX (optionnel):
	•	/modules/entries/ajax_accounts.php
	•	/modules/entries/ajax_third_parties.php

⸻

13. Critères d’acceptation
	•	Un utilisateur admin peut paramétrer société, plan comptable, journaux, TVA, périodes.
	•	Un comptable peut créer une pièce en brouillon, ajouter des lignes, sauvegarder, joindre un fichier.
	•	Une pièce ne peut pas être validée si déséquilibrée ou période verrouillée.
	•	Une fois validée, une pièce est numérotée et devient non modifiable.
	•	Import CSV écritures crée des pièces correctes et affiche un rapport d’erreurs.
	•	Import relevé bancaire crée un relevé et ses lignes.
	•	Rapprochement permet de matcher une ligne relevé avec une ligne d’écriture banque.
	•	Lettrage permet de sélectionner des lignes d’un tiers et de créer un lettrage équilibré.
	•	États affichent des totaux cohérents avec les écritures validées.
	•	Export PDF fonctionne pour pièce, journal, grand livre, balance, TVA.
	•	Verrouillage période empêche toute nouvelle saisie/validation dans la période.
	•	Clôture annuelle génère une pièce d’à-nouveaux équilibrée et consultable dans le nouvel exercice.
