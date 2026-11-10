<?php
/**
 * ============================================================
 *  TEXTES, LOGOS ET TRANSITION DE L'ÉCRAN DE DIFFUSION
 *  C'est ICI qu'on modifie les textes affichés à l'écran.
 *  Ces valeurs sont les textes par défaut : ils se modifient aussi depuis la page « Textes » de l'application (qui a priorité).
 *  Les réglages (logos, transition, compte à rebours) se modifient ici : enregistrez puis F5.
 *  {org}, {n}… sont remplacés automatiquement : ne les supprimez pas.
 *  Le nom du concours, la date et l'organisation se changent dans le Tableau de bord.
 *  Les noms des phases et les pondérations (60/40, 80/20) sont dans app/bootstrap.php (PHASES, WEIGHTS).
 * ============================================================
 */
return [
    // --- Logos : fichiers à placer dans public/assets/img/ (PNG transparent conseillé, < 300 Ko, ~700 px de large).
    //     Un logo téléversé dans le Tableau de bord a priorité sur ces fichiers.
    'logos' => [
        'center' => '/assets/img/milieu.png',   // logo du milieu (accueil et transition)
        'left'   => '/assets/img/haut_gauche.png',   // en haut à gauche, affiché dans toutes les scènes
        'right'  => '/assets/img/haut_droite.png',   // en haut à droite, affiché dans toutes les scènes
    ],

    // --- Vitesse du comptage des chiffres (millisecondes ; plus petit = plus rapide)
    'count_ms' => 800,

    // --- Transition animée entre deux scènes (logos + balayage doré)
    'transition' => [
        'enabled' => true,   // false = changement de scène direct
        'hold_ms' => 350,    // durée pendant laquelle les logos restent visibles (millisecondes)
        'in_ms'   => 280,    // durée du balayage d'entrée
        'out_ms'  => 250,    // durée du balayage de sortie
    ],
    'transition_text' => '',   // texte affiché sous le logo pendant ce balayage (vide = aucun)

    // --- Générique des logos (scène « 08 ») : vitesse (1 = normale, 0.7 = plus rapide, 1.5 = plus lent) et texte de la phase affiché après les logos
    'jingle' => ['speed' => 1.0],
    'jingle_p1' => 'Phase 1', 'jingle_p1_sub' => 'Éliminatoires',
    'jingle_p2' => 'Phase 2', 'jingle_p2_sub' => 'Demi-finale',
    'jingle_p3' => 'Phase 3', 'jingle_p3_sub' => 'Finale',

    // --- Accueil
    'accueil_org' => '{org}',

    // --- Dépouillement
    'depouillement_title' => 'Dépouillement en cours',
    'depouillement_sub'   => 'Merci de patienter, les résultats seront annoncés dans quelques instants.',

    // --- Titres des classements
    'classement_p1' => 'Classement du jury',
    'classement_p2' => 'Classement · demi-finale',
    'classement_p3' => 'Classement final',

    // --- Colonnes / cartes (le pourcentage de pondération est ajouté automatiquement)
    'col_public'   => 'Vote du public',
    'col_jury'     => 'Jury',
    'col_jury_p1'  => 'Note du jury',
    'col_average'  => 'Moyenne',
    'juror_label'  => 'Jury {n}',
    'col_total'    => 'Score final',
    'col_rank'     => 'Classement',
    'candidate'    => 'CANDIDAT N°',
    'votes'        => 'votes',
    'pts'          => 'pts',
    'points'       => 'points',
    'tie'          => 'ex æquo',

    // --- Qualifiés
    'qualifies_p1'   => 'Qualifiés pour la phase 2',
    'qualifies_p2'   => 'Les finalistes',
    'badge_qualified' => 'QUALIFIÉ',
    'badge_finalist'  => 'FINALISTE',

    // --- Podium et gagnant
    'podium_title'  => 'Podium',
    'winner_title'  => 'Grand gagnant',
    'winners_tie'   => 'Vainqueurs ex æquo',
    'winner_suspense' => 'Et le grand gagnant est…',
    'winner_suspense_ms' => 3600,   // durée du suspense avant la révélation du gagnant (millisecondes, 0 = sans suspense)
];
