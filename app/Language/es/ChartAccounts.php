<?php

return [
    // Botones
    'btn_newAccount'        => 'Nueva Cuenta',
    'btn_filter'            => 'Filtrar',
    'btn_back'              => 'Volver',
    'btn_cancel'            => 'Cancelar',
    'btn_createAccount'     => 'Crear Cuenta',
    'btn_updateAccount'     => 'Actualizar Cuenta',

    // Títulos de párrafo
    'pageHeadingCreate'     => 'Crear Cuenta Contable',
    'pageHeadingEdit'       => 'Editar Cuenta Contable',

    // Etiquetas de filtros
    'labelAccountType'      => 'Tipo de Cuenta',
    'labelAllTypes'         => 'Todos los Tipos',
    'labelStatus'           => 'Estado',
    'labelAll'              => 'Todos',
    'labelActive'           => 'Activo',
    'labelInactive'         => 'Inactivo',
    'labelEpiscopalSection' => 'Sección Episcopal',
    'labelAllSections'      => 'Todas las Secciones',
    'sectionBalanceSheet' => 'Balance General (1000s-3000s)',
    'sectionIncomeUnrestricted' => 'Ingresos No Restringidos (4000s)',
    'sectionIncomeRestricted' => 'Ingresos Restringidos/Designados (5000-5399)',
    'sectionIncomeTransfers' => 'Transferencias de Fondos (5400s)',
    'sectionIncomeGrants' => 'Seguimiento de Subvenciones (5500s)',
    'sectionExpenseOutreach' => 'Gastos de Alcance (6000s)',
    'sectionExpenseMinistry' => 'Ministerio y Programa (7000-7499)',
    'sectionExpensePayroll' => 'Nómina y Cargas (7500s)',
    'sectionExpenseGovernance' => 'Gobernanza (7600s)',
    'sectionExpenseOperations' => 'Operaciones e Instalaciones (8000s)',
    'sectionMultiCampus' => 'Cuentas Sufijo Multicampus',

    // Encabezados de tabla
    'colCode'               => 'Código',
    'colName'               => 'Nombre',
    'colType'               => 'Tipo',
    'colCategory'           => 'Categoría',
    'colBudgetable'         => 'Presupuestable',
    'colStatus'             => 'Estado',
    'colSort'               => 'Orden',
    'colActions'            => 'Acciones',
    'actionToggle'          => 'Cambiar estado',

    // Valores de celda
    'valueYes'              => 'Sí',
    'valueNo'               => 'No',

    // Estado vacío
    'noAccountsFound'       => 'No se encontraron cuentas contables.',

    // Opciones de tipo de cuenta
    'typeIncome'            => 'Ingreso',
    'typeExpense'           => 'Gasto',
    'typeAsset'             => 'Activo',
    'typeLiability'         => 'Pasivo',
    'typeEquity'            => 'Capital',

    // Etiquetas de formulario
    'labelCode'             => 'Código',
    'labelName'             => 'Nombre',
    'labelDefaultCategory'  => 'Categoría Predeterminada',
    'labelSortOrder'        => 'Orden de Clasificación',
    'labelAllowBudgeting'   => 'Permitir presupuesto',
    'labelDescription'      => 'Descripción',
    'labelParentAccount'    => 'Cuenta Principal',

    // Jerarquía y Niveles
    'colTier'               => 'Nivel',
    'tierMaster'            => 'Maestro TEC',
    'tierDiocese'           => 'Diócesis',
    'tierParish'            => 'Parroquia',
    'readOnly'              => 'Solo lectura',
    'canonicalMasterBadge'  => 'Maestro Canónico',
    'dioceseScopeNotice'    => 'Las cuentas diocesanas amplían el plan de cuentas maestro de la Iglesia Episcopal para todas las parroquias de la diócesis.',
    'parishScopeNotice'     => 'Las cuentas parroquiales amplían el plan de cuentas diocesano y maestro de la Iglesia Episcopal.',
    'parishAccountCreated'  => 'Cuenta contable parroquial creada exitosamente.',
    'parishAccountUpdated'  => 'Cuenta contable parroquial actualizada exitosamente.',
    'parishAccountStatusUpdated' => 'Estado de la cuenta contable parroquial actualizado exitosamente.',
    'parishCannotEditDioceseAccount' => 'Las cuentas diocesanas y maestras son de solo lectura para las parroquias.',
    'parishAccountNotFound' => 'Cuenta contable no encontrada.',
    'parishAccountCodeExists' => 'El código de cuenta ya existe en su catálogo de cuentas.',
    'parishAccountCreateFailed' => 'No se pudo crear la cuenta contable parroquial.',
    'parishAccountUpdateFailed' => 'No se pudo actualizar la cuenta contable parroquial.',
    'systemAccountReadOnly' => 'Las cuentas maestras de la Iglesia Episcopal son canónicas y de solo lectura.',
];
