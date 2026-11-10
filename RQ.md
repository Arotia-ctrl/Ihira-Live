  Voici une réflexion ciblée pour fluidifier le système au maximum et lui donner une finition professionnelle de
  niveau régie TV, sans toucher aux fichiers pour l’instant :
  ──────
  ### 1. Fluidifier le système au maximum (Performance & Réactivité)

  Le serveur local actuel utilise le serveur intégré de PHP (php -S), qui est mono-threadé (il traite une seule
  requête à la fois). Actuellement, OBS + l'aperçu régie + la régie envoient des requêtes toutes les 700 ms à 1 200
  ms. Voici comment rendre tout instantané :

  1. Activer le mode WAL de SQLite (PRAGMA journal_mode = WAL;)
      • Pourquoi ? Par défaut, SQLite verrouille tout le fichier lors d'une écriture (ex: enregistrement d'une note ou
      changement de scène). En activant le mode WAL (Write-Ahead Logging), les lectures (l'écran OBS) et les écritures
      (la régie) se font en parallèle sans jamais se bloquer.
  2. Cache léger sur l'API state.php (Éviter de recalculer tout le classement à chaque seconde)
      • Pourquoi ? Actuellement, chaque 700 ms, PHP relit les tables, refait tous les calculs de moyenne et
      pondération.
      • Gain : Si le numéro de séquence seq et la phase n'ont pas bougé, renvoyer instantanément le JSON déjà préparé
      en mémoire ou via un header HTTP 304 Not Modified. L'usage CPU de votre PC passera à quasi 0 %.
  3. Accélération matérielle GPU sur OBS (display.css)
      • Pourquoi ? Forcer le rendu GPU sur les éléments animés (le balayage doré, les logos qui glissent, le comptage
      des chiffres) avec will-change: transform, opacity; et transform: translateZ(0);. Cela garantit un 60 FPS
      constant sans micro-saccades, même si le PC diffuse en même temps sur OBS.

  ──────
  ### 2. Confort et anti-stress pour l'opérateur en Régie (Le Jour J)

  Le stress en direct vient souvent des manipulations à la souris dans l'urgence :

  1. Raccourcis clavier en régie
      • Pouvoir piloter sans souris :
          • Flèche Droite : Afficher le candidat suivant.
          • Flèche Gauche : Candidat précédent.
          • Espace : Déclencher l'étape suivante de révélation (1 → 2 → 3).
          • Échap : Écran neutre / Masquage d'urgence immédiat.

  2. Alerte visuelle en cas d'ex æquo critique
      • Si 2 compétiteurs arrivent à égalité stricte pour la dernière place qualificative (ex: 5e et 6e ex æquo alors
      qu'il n'y a que 5 qualifiés), afficher une bannière en régie pour alerter les juges avant publication.
  3. Bouton de confirmation / sécurité avant un "Reset" ou un "Déverrouillage" accidentel
      • Éviter qu'un mauvais clic pendant le spectacle ne remette les notes à zéro.

  ──────
  ### 3. Habillage scénique & Effet "Show TV"

  1. Rappel discret de l'Église / Organisation sur le Podium et le Grand Gagnant
      • Comme on l'a fait sur le résultat individuel, ajouter en sous-titre élégant le nom de l'église sur la carte du
      grand gagnant (ex : « Vainqueur : Nom de Scène · FJKM Namehana »).
  2. Effets sonores optionnels (Jingles audio)
      • Prévoir une option (activable/désactivable en régie) pour jouer un court effet sonore via la page web :
          • Roulement de tambour pendant le suspense du gagnant.
          • Fanfare / Cymbales lors de l'apparition des confettis.


  ──────
  ### 4. Sauvegarde & Procès-verbal officiel (Transparence du Jury)

  1. Sauvegarde automatique horodatée à chaque validation
      • Dès qu'une phase est validée, le système duplique automatiquement la base : database/backup-phase1-2026-11-
      15_16h30.sqlite. En cas de fausse manipulation ou coupure de courant, rien n'est jamais perdu.
  2. Export / Impression du Procès-verbal (PV des résultats)
      • Un bouton « Imprimer la feuille de résultats » qui génère une page A4 propre et imprimable avec le tableau des
      notes de chaque juré, les moyennes, le vote public et le classement final, prête à être signée par le président
      du jury.

### 5. ajouter un portail destinees au jury , pour qu'ils n'ont qu'a taper leur notes dedans (beta)

  ──────
  Dites-moi quels points parmi ceux-ci vous semblent les plus prioritaires ou utiles pour votre événement !
