<?php
/**
 * TLS Operations Menu Structure
 * Defines the complete menu hierarchy with security permissions
 * 
 * @author Tony Lyle
 * @version 1.0
 */

return [
    'file' => [
        'label' => 'File',
        'icon' => 'bi-files',
        'items' => [
            'mnuExit' => [
                'label' => 'Exit',
                'icon' => 'bi-box-arrow-right',
                'url' => '/logout.php'
            ]
        ]
    ],
    
    'accounting' => [
        'label' => 'Accounting',
        'icon' => 'bi-calculator',
        'items' => [
            'gl' => [
                'label' => 'G/L',
                'icon' => 'bi-journal-text',
                'items' => [
                    'mnuCOAMaint' => [
                        'label' => 'Chart of Account Maintenance',
                        'url' => '/accounting/coa-maintenance.php'
                    ],
                    'mnuGLAccoutHistory' => [
                        'label' => 'Account History',
                        'url' => '/accounting/account-history.php'
                    ],
                    'mnuTransactionSearch' => [
                        'label' => 'Transaction Search',
                        'url' => '/accounting/transaction-search.php'
                    ],
                    'separator1' => ['separator' => true],
                    'mnuJE' => [
                        'label' => 'Journal Entry',
                        'url' => '/accounting/journal-entry.php'
                    ],
                    'mnuBankRec' => [
                        'label' => 'Bank Reconciliation',
                        'url' => '/accounting/bank-reconciliation.php'
                    ],
                    'separator2' => ['separator' => true],
                    'reports' => [
                        'label' => 'Reports',
                        'icon' => 'bi-file-earmark-text',
                        'items' => [
                            'mnuChartofAccounts' => [
                                'label' => 'Chart of Accounts',
                                'url' => '/accounting/reports/chart-of-accounts.php'
                            ],
                            'separator3' => ['separator' => true],
                            'mnuBalanceSheet' => [
                                'label' => 'Balance Sheet',
                                'url' => '/accounting/reports/balance-sheet.php'
                            ],
                            'mnuIncomeStatement' => [
                                'label' => 'Income Statement',
                                'url' => '/accounting/reports/income-statement.php'
                            ],
                            'mnuTrialBalance' => [
                                'label' => 'Trial Balance',
                                'url' => '/accounting/reports/trial-balance.php'
                            ],
                            'mnuDailyBalance' => [
                                'label' => 'Daily Balance Summary',
                                'url' => '/accounting/reports/daily-balance.php'
                            ],
                            'mnuFinStmt' => [
                                'label' => 'All Financial Statements',
                                'url' => '/accounting/reports/financial-statements.php'
                            ],
                            'separator4' => ['separator' => true],
                            'mnuGeneralLedger' => [
                                'label' => 'General Ledger',
                                'url' => '/accounting/reports/general-ledger.php'
                            ],
                            'mnuTransactionJournal' => [
                                'label' => 'Transaction Journal',
                                'url' => '/accounting/reports/transaction-journal.php'
                            ],
                            'mnuOOAR' => [
                                'label' => 'Owner Operator Accounts Receivable',
                                'url' => '/accounting/reports/oo-ar.php'
                            ],
                            'mnuAPJournal' => [
                                'label' => 'AP Journal',
                                'url' => '/accounting/reports/ap-journal.php'
                            ]
                        ]
                    ],
                    'separator5' => ['separator' => true],
                    'mnuGLExport' => [
                        'label' => 'GL Export',
                        'url' => '/accounting/gl-export.php'
                    ]
                ]
            ],
            'ap' => [
                'label' => 'A/P',
                'icon' => 'bi-credit-card',
                'items' => [
                    'mnuVendorMaint' => [
                        'label' => 'Vendor Maintenance',
                        'url' => '/accounting/vendor-maintenance.php'
                    ],
                    'mnuVendorHist' => [
                        'label' => 'Vendor History',
                        'url' => '/accounting/vendor-history.php'
                    ],
                    'mnuInvoiceSearch' => [
                        'label' => 'Invoice Search',
                        'url' => '/accounting/invoice-search.php'
                    ],
                    'separator6' => ['separator' => true],
                    'mnuVoucher' => [
                        'label' => 'Voucher Entry',
                        'url' => '/accounting/voucher-entry.php'
                    ],
                    'mnuAPInvoiceApproval' => [
                        'label' => 'Invoice Approval',
                        'url' => '/accounting/invoice-approval.php'
                    ],
                    'separator7' => ['separator' => true],
                    'mnuPayables' => [
                        'label' => 'Process Checks',
                        'url' => '/accounting/process-checks.php'
                    ],
                    'separator8' => ['separator' => true],
                    'mnuProcessICCheck' => [
                        'label' => 'Process Inter-Company Checks',
                        'url' => '/accounting/intercompany-checks.php'
                    ]
                ]
            ],
            'ar' => [
                'label' => 'A/R',
                'icon' => 'bi-receipt',
                'items' => [
                    'mnuCollections' => [
                        'label' => 'Collections Work',
                        'url' => '/accounting/collections.php'
                    ],
                    'mnuReapplyPmt' => [
                        'label' => 'Reapply Payments',
                        'url' => '/accounting/reapply-payments.php'
                    ],
                    'mnuARDeposit' => [
                        'label' => 'Deposit Entry',
                        'url' => '/accounting/deposit-entry.php'
                    ],
                    'mnuDepositLookup' => [
                        'label' => 'Deposit Lookup',
                        'url' => '/accounting/deposit-lookup.php'
                    ],
                    'mnuLoadLookup' => [
                        'label' => 'Load Lookup',
                        'url' => '/accounting/load-lookup.php'
                    ],
                    'separator9' => ['separator' => true],
                    'mnuBillingEntry' => [
                        'label' => 'Billing Entry',
                        'url' => '/accounting/billing-entry.php'
                    ],
                    'mnuCustMaint' => [
                        'label' => 'Customer Maintenance',
                        'url' => '/accounting/customer-maintenance.php'
                    ],
                    'mnuloadreg' => [
                        'label' => 'Loads Billed Register',
                        'url' => '/accounting/loads-billed-register.php'
                    ],
                    'separator10' => ['separator' => true],
                    'ar_reports' => [
                        'label' => 'Reports',
                        'items' => [
                            'mnuUnbilledLoads' => [
                                'label' => 'Unbilled Loads',
                                'url' => '/accounting/reports/unbilled-loads.php'
                            ],
                            'mnuUnbilledWithPaperwork' => [
                                'label' => 'Unbilled Loads with Paperwork',
                                'url' => '/accounting/reports/unbilled-with-paperwork.php'
                            ]
                        ]
                    ],
                    'separator11' => ['separator' => true],
                    'mnuPrintBatch' => [
                        'label' => 'Print Batch of Invoices',
                        'url' => '/accounting/print-batch-invoices.php'
                    ]
                ]
            ],
            'pr' => [
                'label' => 'P/R',
                'icon' => 'bi-people',
                'items' => [
                    'mnuDriverPRMaint' => [
                        'label' => 'Driver Payroll Maintenance',
                        'url' => '/accounting/driver-pr-maintenance.php'
                    ],
                    'mnuTrialDriverPR' => [
                        'label' => 'Run Trial Driver PR',
                        'url' => '/accounting/trial-driver-pr.php'
                    ],
                    'mnuPreviewDriverPR' => [
                        'label' => 'Preview Driver PR',
                        'url' => '/accounting/preview-driver-pr.php'
                    ],
                    'mnuFinalizeDriverPR' => [
                        'label' => 'Finalize Driver PR',
                        'url' => '/accounting/finalize-driver-pr.php'
                    ]
                ]
            ]
        ]
    ],
    
    'dispatch' => [
        'label' => 'Dispatch',
        'icon' => 'bi-truck',
        'items' => [
            'mnuEDILoads' => [
                'label' => 'Available EDI Loads',
                'url' => '/dispatch/edi-loads.php'
            ],
            'separator12' => ['separator' => true],
            'mnuLoadEntry' => [
                'label' => 'Load Entry',
                'url' => '/dispatch/load-entry.php'
            ],
            'mnuAvailLoads' => [
                'label' => 'Loads Available for Dispatch',
                'url' => '/dispatch/available-loads.php'
            ],
            'mnuLoadInq' => [
                'label' => 'Load Inquiry',
                'url' => '/dispatch/load-inquiry.php'
            ],
            'mnuCreditCheck' => [
                'label' => 'Customer Credit Check',
                'url' => '/dispatch/credit-check.php'
            ],
            'mnuExceptionStatusList' => [
                'label' => 'Exception Status List',
                'url' => '/dispatch/exception-status.php'
            ],
            'separator13' => ['separator' => true],
            'mnuLookupLoads' => [
                'label' => 'Lookup Loads by Location',
                'url' => '/dispatch/lookup-loads.php'
            ],
            'mnuDailyCount' => [
                'label' => 'Daily Count',
                'url' => '/dispatch/daily-count.php'
            ],
            'mnuUnitHistory' => [
                'label' => 'Unit History',
                'url' => '/dispatch/unit-history.php'
            ],
            'mnuUnitLastDel' => [
                'label' => 'Unit Last Delivery',
                'url' => '/dispatch/unit-last-delivery.php'
            ],
            'mnuAgentHistory' => [
                'label' => 'Agent History',
                'url' => '/dispatch/agent-history.php'
            ],
            'mnuLocationMaint' => [
                'label' => 'Location Maintenance',
                'url' => '/dispatch/location-maintenance.php'
            ],
            'mnuTrailerPool' => [
                'label' => 'Trailer Pool Maintenance',
                'url' => '/dispatch/trailer-pool.php'
            ],
            'mnuLoadTrailerStatus' => [
                'label' => 'Load Trailer Status',
                'url' => '/dispatch/load-trailer-status.php'
            ],
            'separator14' => ['separator' => true],
            'mnuEFSLoadCash' => [
                'label' => 'EFS Load Cash',
                'url' => '/dispatch/efs-load-cash.php'
            ]
        ]
    ],
    
    'logistics' => [
        'label' => 'Logistics',
        'icon' => 'bi-diagram-3',
        'items' => [
            'mnuAvailEDI' => [
                'label' => 'Available EDI Loads',
                'url' => '/logistics/edi-loads.php'
            ],
            'separator15' => ['separator' => true],
            'mnuLoadEntryLog' => [
                'label' => 'Load Entry',
                'url' => '/logistics/load-entry.php'
            ],
            'mnuAvailTrucks' => [
                'label' => 'Available Trucks/Loads',
                'url' => '/logistics/available-trucks.php'
            ],
            'mnuLoadInqLog' => [
                'label' => 'Load Inquiry',
                'url' => '/logistics/load-inquiry.php'
            ],
            'mnuCreditCheckLog' => [
                'label' => 'Customer Credit Check',
                'url' => '/logistics/credit-check.php'
            ],
            'mnuLookupLoadsLog' => [
                'label' => 'Lookup Loads by Location',
                'url' => '/logistics/lookup-loads.php'
            ],
            'mnuBrokerTracking' => [
                'label' => 'Broker Tracking',
                'url' => '/logistics/broker-tracking.php'
            ],
            'separator16' => ['separator' => true],
            'mnuCarrierMaint' => [
                'label' => 'Carrier Maintenance',
                'url' => '/logistics/carrier-maintenance.php'
            ],
            'mnuCarrierHistory' => [
                'label' => 'Carrier History',
                'url' => '/logistics/carrier-history.php'
            ],
            'mnuCarrierCheck' => [
                'label' => 'Carrier Check Lookup',
                'url' => '/logistics/carrier-check.php'
            ],
            'mnuPrintBrokerConf' => [
                'label' => 'Reprint Broker Confirmation',
                'url' => '/logistics/reprint-confirmation.php'
            ],
            'settlements' => [
                'label' => 'Process Settlements',
                'items' => [
                    'mnuLogTrial' => [
                        'label' => 'Run Trial Settlements',
                        'url' => '/logistics/settlements/trial.php'
                    ],
                    'mnuLogPreview' => [
                        'label' => 'Preview Settlements',
                        'url' => '/logistics/settlements/preview.php'
                    ],
                    'mnuLogFinal' => [
                        'label' => 'Finalize Settlements',
                        'url' => '/logistics/settlements/finalize.php'
                    ],
                    'mnuLogPrint' => [
                        'label' => 'Print Settlements',
                        'url' => '/logistics/settlements/print.php'
                    ],
                    'separator17' => ['separator' => true],
                    'mnuReassignCarrierLoad' => [
                        'label' => 'Re-Assign Carrier Load',
                        'url' => '/logistics/settlements/reassign.php'
                    ]
                ]
            ]
        ]
    ],
    
    'mobile' => [
        'label' => 'Mobile',
        'icon' => 'bi-phone',
        'items' => [
            'mnuMobileSendMessage' => [
                'label' => 'Send Mobile Message',
                'url' => '/mobile/send-message.php'
            ],
            'separator18' => ['separator' => true],
            'mnuMobileMessages' => [
                'label' => 'Message Status',
                'url' => '/mobile/message-status.php'
            ],
            'mnuPosRptHist' => [
                'label' => 'Position Report History',
                'url' => '/mobile/position-history.php'
            ],
            'mnuMessageSearch' => [
                'label' => 'Message Search',
                'url' => '/mobile/message-search.php'
            ]
        ]
    ],
    
    'imaging' => [
        'label' => 'Imaging',
        'icon' => 'bi-images',
        'items' => [
            'mnuImageAudit' => [
                'label' => 'Image Audit',
                'url' => '/imaging/audit.php'
            ],
            'mnuImageExceptions' => [
                'label' => 'Image Exceptions',
                'url' => '/imaging/exceptions.php'
            ]
        ]
    ],
    
    'reports' => [
        'label' => 'Reports',
        'icon' => 'bi-file-earmark-bar-graph',
        'items' => [
            'mnuPrintJobs' => [
                'label' => 'Print Jobs',
                'url' => '/reports/print-jobs.php'
            ],
            'separator19' => ['separator' => true],
            'submit_reports' => [
                'label' => 'Submit Reports',
                'items' => [
                    'mnuDriverMiles' => [
                        'label' => 'Driver Miles',
                        'url' => '/reports/submit/driver-miles.php'
                    ],
                    'mnuSubmitReportUnitMiles' => [
                        'label' => 'Unit Miles',
                        'url' => '/reports/submit/unit-miles.php'
                    ]
                ]
            ],
            'mnuSubmitReport' => [
                'label' => 'Submit Report Job',
                'url' => '/reports/submit-job.php'
            ],
            'mnuAgingReport' => [
                'label' => 'Aging Report',
                'url' => '/reports/aging.php'
            ],
            'mnuCBAgingReport' => [
                'label' => 'C/B Aging Report',
                'url' => '/reports/cb-aging.php'
            ],
            'mnuCustRevSum' => [
                'label' => 'Customer Revenue Summary',
                'url' => '/reports/customer-revenue.php'
            ],
            'mnuUnitRev' => [
                'label' => 'Unit Revenue Summary',
                'url' => '/reports/unit-revenue.php'
            ],
            'mnuUnitStateReport' => [
                'label' => 'Unit State Mileage Summary',
                'url' => '/reports/unit-state-mileage.php'
            ],
            'mnuReloadReport' => [
                'label' => 'Reload Report',
                'url' => '/reports/reload.php'
            ],
            'mnuTrafficLanes' => [
                'label' => 'Traffic Lanes',
                'url' => '/reports/traffic-lanes.php'
            ],
            'separator20' => ['separator' => true],
            'mnuPosMap' => [
                'label' => 'Unit/Load Positions',
                'url' => '/reports/positions.php'
            ],
            'mnuOwnerLabels' => [
                'label' => 'Owner/Driver Labels',
                'url' => '/reports/owner-labels.php'
            ],
            'mnuTeamDriverList' => [
                'label' => 'Team Driver List (Excel)',
                'url' => '/reports/team-drivers.php'
            ],
            'mnuActiveDriverList' => [
                'label' => 'Active Driver Address Listing',
                'url' => '/reports/active-drivers.php'
            ],
            'mnuActiveCarrierList' => [
                'label' => 'Active Carrier Address Listing',
                'url' => '/reports/active-carriers.php'
            ],
            'mnuTractorList' => [
                'label' => 'Tractor Listing',
                'url' => '/reports/tractors.php'
            ],
            'mnuTrailerList' => [
                'label' => 'Trailer Listing',
                'url' => '/reports/trailers.php'
            ],
            'mnuDriverInsRpt' => [
                'label' => 'Driver Insurance Report',
                'url' => '/reports/driver-insurance.php'
            ],
            'separator21' => ['separator' => true],
            'mnuRecap' => [
                'label' => 'Weekly Recap',
                'url' => '/reports/weekly-recap.php'
            ],
            'mnuPrint1099' => [
                'label' => 'Print 1099',
                'url' => '/reports/print-1099.php'
            ],
            'mnuSMDRReport' => [
                'label' => 'SMDR Report',
                'url' => '/reports/smdr.php'
            ]
        ]
    ],
    
    'safety' => [
        'label' => 'Safety',
        'icon' => 'bi-shield-check',
        'items' => [
            'mnuAgentMaint' => [
                'label' => 'Agent Maintenance',
                'url' => '/safety/agent-maintenance.php'
            ],
            'mnuDriverMaint' => [
                'label' => 'Driver Maintenance',
                'url' => '/safety/driver-maintenance.php'
            ],
            'mnuOwnerMaint' => [
                'label' => 'Owner Maintenance',
                'url' => '/safety/owner-maintenance.php'
            ],
            'mnuUnitMaint' => [
                'label' => 'Unit Maintenance',
                'url' => '/safety/unit-maintenance.php'
            ],
            'mnuTractorTrailer' => [
                'label' => 'Tractor/Trailer Lookup',
                'url' => '/safety/tractor-trailer-lookup.php'
            ],
            'mnuDriverPictures' => [
                'label' => 'Load Driver Pictures',
                'url' => '/safety/driver-pictures.php'
            ],
            'separator22' => ['separator' => true],
            'mnuPayrollMaint' => [
                'label' => 'Payroll Maintenance',
                'url' => '/safety/payroll-maintenance.php'
            ],
            'mnuUnitCheck' => [
                'label' => 'Unit Check Lookup',
                'url' => '/safety/unit-check-lookup.php'
            ],
            'mnuAgentCheck' => [
                'label' => 'Agent Check Lookup',
                'url' => '/safety/agent-check-lookup.php'
            ],
            'mnuFuel' => [
                'label' => 'Fuel Receipts',
                'url' => '/safety/fuel-receipts.php'
            ],
            'separator23' => ['separator' => true],
            'mnuEFSMoneyCode' => [
                'label' => 'Issue EFS Money Code (Paper Check)',
                'url' => '/safety/efs-money-code.php'
            ],
            'efs_maintenance' => [
                'label' => 'EFS Maintenance',
                'items' => [
                    'mnuEFSPolicyMaintenance' => [
                        'label' => 'EFS Policy Maintenance',
                        'url' => '/safety/efs/policy-maintenance.php'
                    ],
                    'mnuEFSCardMaintenance' => [
                        'label' => 'EFS Card Maintenance',
                        'url' => '/safety/efs/card-maintenance.php'
                    ]
                ]
            ],
            'separator24' => ['separator' => true],
            'owner_settlements' => [
                'label' => 'Process Owner Settlements',
                'items' => [
                    'mnuCreateRepeating' => [
                        'label' => 'Create Repeating Deductions',
                        'url' => '/safety/settlements/repeating-deductions.php'
                    ],
                    'mnuTrialOwnerPR' => [
                        'label' => 'Run Trial Settlements',
                        'url' => '/safety/settlements/trial.php'
                    ],
                    'mnuPreviewOwnerPR' => [
                        'label' => 'Preview Settlements',
                        'url' => '/safety/settlements/preview.php'
                    ],
                    'mnuFinalizeOwnerPR' => [
                        'label' => 'Finalize Settlements',
                        'url' => '/safety/settlements/finalize.php'
                    ],
                    'mnuPrintOwnerPR' => [
                        'label' => 'Print Checks',
                        'url' => '/safety/settlements/print-checks.php'
                    ],
                    'mnuPrintOwnerDD' => [
                        'label' => 'Print Direct Deposits',
                        'url' => '/safety/settlements/print-deposits.php'
                    ]
                ]
            ]
        ]
    ],
    
    'utilities' => [
        'label' => 'Utilities',
        'icon' => 'bi-tools',
        'items' => [
            'mnuUserMaint' => [
                'label' => 'User Maintenance',
                'url' => '/utilities/user-maintenance.php'
            ],
            'mnuBackupRestore' => [
                'label' => 'Backup/Restore',
                'url' => '/utilities/backup-restore.php'
            ],
            'mnuPreferences' => [
                'label' => 'System Preferences',
                'url' => '/utilities/preferences.php'
            ]
        ]
    ]
];