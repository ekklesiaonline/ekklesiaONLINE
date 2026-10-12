<?php

return [
    // Boutons
    'btn_newAccount'        => 'Nouveau Compte',
    'btn_filter'            => 'Filtrer',
    'btn_back'              => 'Retour',
    'btn_cancel'            => 'Annuler',
    'btn_createAccount'     => 'Créer le Compte',
    'btn_updateAccount'     => 'Mettre à Jour le Compte',

    // Titres de page
    'pageHeadingCreate'     => 'Créer un Compte Comptable',
    'pageHeadingEdit'       => 'Modifier le Compte Comptable',

    // Étiquettes de filtre
    'labelAccountType'      => 'Type de Compte',
    'labelAllTypes'         => 'Tous les Types',
    'labelStatus'           => 'Statut',
    'labelAll'              => 'Tous',
    'labelActive'           => 'Actif',
    'labelInactive'         => 'Inactif',
    'labelEpiscopalSection' => 'Section Épiscopale',
    'labelAllSections'      => 'Toutes les Sections',
    'sectionBalanceSheet' => 'Bilan (1000s-3000s)',
    'sectionIncomeUnrestricted' => 'Revenus Non Affectés (4000s)',
    'sectionIncomeRestricted' => 'Revenus Affectés/Désignés (5000-5399)',
    'sectionIncomeTransfers' => 'Transferts de Fonds (5400s)',
    'sectionIncomeGrants' => 'Suivi des Subventions (5500s)',
    'sectionExpenseOutreach' => 'Dépenses de Sensibilisation (6000s)',
    'sectionExpenseMinistry' => 'Ministère et Programme (7000-7499)',
    'sectionExpensePayroll' => 'Masse Salariale et Charges (7500s)',
    'sectionExpenseGovernance' => 'Gouvernance (7600s)',
    'sectionExpenseOperations' => 'Opérations et Installations (8000s)',
    'sectionMultiCampus' => 'Comptes à Suffixe Multi-campus',

    // En-têtes de tableau
    'colCode'               => 'Code',
    'colName'               => 'Nom',
    'colType'               => 'Type',
    'colCategory'           => 'Catégorie',
    'colBudgetable'         => 'Budgétisable',
    'colStatus'             => 'Statut',
    'colSort'               => 'Ordre',
    'colActions'            => 'Actions',
    'actionToggle'          => 'Changer le statut',

    // Valeurs de cellule
    'valueYes'              => 'Oui',
    'valueNo'               => 'Non',

    // État vide
    'noAccountsFound'       => 'Aucun compte comptable trouvé.',

    // Options de type de compte
    'typeIncome'            => 'Revenu',
    'typeExpense'           => 'Dépense',
    'typeAsset'             => 'Actif',
    'typeLiability'         => 'Passif',
    'typeEquity'            => 'Capitaux Propres',

    // Étiquettes de formulaire
    'labelCode'             => 'Code',
    'labelName'             => 'Nom',
    'labelDefaultCategory'  => 'Catégorie par Défaut',
    'labelSortOrder'        => 'Ordre de Tri',
    'labelAllowBudgeting'   => 'Autoriser le budget',
    'labelDescription'      => 'Description',
    'labelParentAccount'    => 'Compte Parent',
    'selectAccountType'     => 'Sélectionnez un type de compte',
    'selectParentAccount'   => 'Aucun (compte autonome)',
    'placeholderCode'       => 'p. ex. 01',
    'helpAccountType'       => 'Sélectionnez la classification financière de ce compte.',
    'helpParentAccount'     => 'Choisissez un compte actif du type sélectionné pour créer un sous-compte.',
    'helpCode'              => 'Saisissez le suffixe unique ; le code du compte parent est ajouté automatiquement.',
    'helpName'              => 'Saisissez un nom clair qui identifie le compte dans les rapports et transactions.',
    'helpDefaultCategory'   => 'Regroupez éventuellement ce compte sous une catégorie de rapport.',
    'helpSortOrder'         => 'Les nombres inférieurs apparaissent avant les nombres supérieurs dans le plan.',
    'helpAllowBudgeting'    => 'Activez ce compte pour les postes du budget paroissial.',
    'helpActive'            => 'Les comptes actifs sont disponibles pour les nouvelles activités financières.',
    'helpDescription'       => 'Décrivez éventuellement le but et l’utilisation prévue de ce compte.',

    // Hiérarchie et Niveaux
    'colTier'               => 'Niveau',
    'tierMaster'            => 'Maître TEC',
    'tierDiocese'           => 'Diocèse',
    'tierParish'            => 'Paroisse',
    'readOnly'              => 'Lecture seule',
    'canonicalMasterBadge'  => 'Maître Canonique',
    'dioceseScopeNotice'    => 'Les comptes diocésains élargissent le plan comptable maître de l\'Église épiscopale pour toutes les paroisses du diocèse.',
    'parishScopeNotice'     => 'Les comptes paroissiaux élargissent le plan comptable diocésain et maître de l\'Église épiscopale.',
    'parishAccountCreated'  => 'Compte comptable paroissial créé avec succès.',
    'parishAccountUpdated'  => 'Compte comptable paroissial mis à jour avec succès.',
    'parishAccountStatusUpdated' => 'Statut du compte comptable paroissial mis à jour avec succès.',
    'parishCannotEditDioceseAccount' => 'Les comptes diocésains et maîtres sont en lecture seule pour les paroisses.',
    'parishAccountNotFound' => 'Compte comptable introuvable.',
    'parishAccountCodeExists' => 'Le code de compte existe déjà dans votre plan comptable.',
    'parishAccountCreateFailed' => 'Impossible de créer le compte comptable paroissial.',
    'parishAccountUpdateFailed' => 'Impossible de mettre à jour le compte comptable paroissial.',
    'systemAccountReadOnly' => 'Les comptes maîtres de l\'Église épiscopale sont canoniques et en lecture seule.',
];
