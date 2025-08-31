<?php
/**
 * TLS Operations Menu Structure
 * Defines the complete menu hierarchy with security permissions
 * 
 * @author Tony Lyle
 * @version 1.0
 */

return array (
  'accounting' => 
  array (
    'label' => 'Accounting',
    'icon' => 'bi-calculator',
    'items' => 
    array (
      'gl' => 
      array (
        'label' => 'G/L',
        'icon' => 'bi-journal-text',
        'items' => 
        array (
          'mnuCOAMaint' => 
          array (
            'label' => 'Chart of Account Maintenance',
          ),
          'mnuGLAccoutHistory' => 
          array (
            'label' => 'Account History',
          ),
          'mnuTransactionSearch' => 
          array (
            'label' => 'Transaction Search',
          ),
          'separator1' => 
          array (
            'separator' => true,
          ),
          'mnuJE' => 
          array (
            'label' => 'Journal Entry',
          ),
          'mnuBankRec' => 
          array (
            'label' => 'Bank Reconciliation',
          ),
          'separator2' => 
          array (
            'separator' => true,
          ),
          'reports' => 
          array (
            'label' => 'Reports',
            'icon' => 'bi-file-earmark-text',
            'items' => 
            array (
              'mnuChartofAccounts' => 
              array (
                'label' => 'Chart of Accounts',
              ),
              'separator3' => 
              array (
                'separator' => true,
              ),
              'mnuBalanceSheet' => 
              array (
                'label' => 'Balance Sheet',
              ),
              'mnuIncomeStatement' => 
              array (
                'label' => 'Income Statement',
              ),
              'mnuTrialBalance' => 
              array (
                'label' => 'Trial Balance',
              ),
              'mnuDailyBalance' => 
              array (
                'label' => 'Daily Balance Summary',
              ),
              'mnuFinStmt' => 
              array (
                'label' => 'All Financial Statements',
              ),
              'separator4' => 
              array (
                'separator' => true,
              ),
              'mnuGeneralLedger' => 
              array (
                'label' => 'General Ledger',
              ),
              'mnuTransactionJournal' => 
              array (
                'label' => 'Transaction Journal',
              ),
              'mnuOOAR' => 
              array (
                'label' => 'Owner Operator Accounts Receivable',
              ),
              'mnuAPJournal' => 
              array (
                'label' => 'AP Journal',
              ),
            ),
          ),
          'separator5' => 
          array (
            'separator' => true,
          ),
          'mnuGLExport' => 
          array (
            'label' => 'GL Export',
          ),
        ),
      ),
      'ap' => 
      array (
        'label' => 'A/P',
        'icon' => 'bi-credit-card',
        'items' => 
        array (
          'mnuVendorMaint' => 
          array (
            'label' => 'Vendor Maintenance',
          ),
          'mnuVendorHist' => 
          array (
            'label' => 'Vendor History',
          ),
          'mnuInvoiceSearch' => 
          array (
            'label' => 'Invoice Search',
          ),
          'mnuVoucher' => 
          array (
            'label' => 'Voucher Entry',
          ),
          'mnuAPInvoiceApproval' => 
          array (
            'label' => 'Invoice Approval',
          ),
          'mnuPayables' => 
          array (
            'label' => 'Process Checks',
          ),
          'mnuProcessICCheck' => 
          array (
            'label' => 'Process Inter-Company Checks',
          ),
        ),
      ),
      'ar' => 
      array (
        'label' => 'A/R',
        'icon' => 'bi-receipt',
        'items' => 
        array (
          'mnuCollections' => 
          array (
            'label' => 'Collections Work',
          ),
          'mnuReapplyPmt' => 
          array (
            'label' => 'Reapply Payments',
          ),
          'mnuARDeposit' => 
          array (
            'label' => 'Deposit Entry',
          ),
          'mnuDepositLookup' => 
          array (
            'label' => 'Deposit Lookup',
          ),
          'mnuLoadLookup' => 
          array (
            'label' => 'Load Lookup',
          ),
          'mnuBillingEntry' => 
          array (
            'label' => 'Billing Entry',
          ),
          'mnuCustMaint' => 
          array (
            'label' => 'Customer Maintenance',
          ),
          'mnuloadreg' => 
          array (
            'label' => 'Loads Billed Register',
          ),
          'separator10' => 
          array (
            'separator' => true,
          ),
          'ar_reports' => 
          array (
            'label' => 'Reports',
            'items' => 
            array (
              'mnuUnbilledLoads' => 
              array (
                'label' => 'Unbilled Loads',
              ),
              'mnuUnbilledWithPaperwork' => 
              array (
                'label' => 'Unbilled Loads with Paperwork',
              ),
            ),
          ),
          'separator11' => 
          array (
            'separator' => true,
          ),
          'mnuPrintBatch' => 
          array (
            'label' => 'Print Batch of Invoices',
          ),
        ),
      ),
      'pr' => 
      array (
        'label' => 'P/R',
        'icon' => 'bi-people',
        'items' => 
        array (
          'mnuDriverPRMaint' => 
          array (
            'label' => 'Driver Payroll Maintenance',
          ),
          'mnuTrialDriverPR' => 
          array (
            'label' => 'Run Trial Driver PR',
          ),
          'mnuPreviewDriverPR' => 
          array (
            'label' => 'Preview Driver PR',
          ),
          'mnuFinalizeDriverPR' => 
          array (
            'label' => 'Finalize Driver PR',
          ),
        ),
      ),
    ),
  ),
  'dispatch' => 
  array (
    'label' => 'Dispatch',
    'icon' => 'bi-truck',
    'items' => 
    array (
      'mnuEDILoads' => 
      array (
        'label' => 'Available EDI Loads',
      ),
      'mnuLoadEntry' => 
      array (
        'label' => 'Load Entry',
      ),
      'mnuAvailLoads' => 
      array (
        'label' => 'Loads Available for Dispatch',
      ),
      'mnuLoadInq' => 
      array (
        'label' => 'Load Inquiry',
      ),
      'mnuCreditCheck' => 
      array (
        'label' => 'Customer Credit Check',
      ),
      'mnuExceptionStatusList' => 
      array (
        'label' => 'Exception Status List',
      ),
      'mnuLookupLoads' => 
      array (
        'label' => 'Lookup Loads by Location',
      ),
      'mnuDailyCount' => 
      array (
        'label' => 'Daily Count',
      ),
      'mnuUnitHistory' => 
      array (
        'label' => 'Unit History',
      ),
      'mnuUnitLastDel' => 
      array (
        'label' => 'Unit Last Delivery',
      ),
      'mnuAgentHistory' => 
      array (
        'label' => 'Agent History',
      ),
      'mnuLocationMaint' => 
      array (
        'label' => 'Location Maintenance',
      ),
      'mnuTrailerPool' => 
      array (
        'label' => 'Trailer Pool Maintenance',
      ),
      'mnuLoadTrailerStatus' => 
      array (
        'label' => 'Load Trailer Status',
      ),
      'mnuEFSLoadCash' => 
      array (
        'label' => 'EFS Load Cash',
      ),
      'mobile' => 
      array (
        'label' => 'Mobile',
        'items' => 
        array (
          'mnuMobileSendMessage' => 
          array (
            'label' => 'Send Mobile Message',
          ),
          'mnuMobileMessages' => 
          array (
            'label' => 'Message Status',
          ),
          'mnuPosRptHist' => 
          array (
            'label' => 'Position Report History',
          ),
          'mnuMessageSearch' => 
          array (
            'label' => 'Message Search',
          ),
        ),
      ),
    ),
  ),
  'logistics' => 
  array (
    'label' => 'Logistics',
    'icon' => 'bi-diagram-3',
    'items' => 
    array (
      'mnuAvailEDI' => 
      array (
        'label' => 'Available EDI Loads',
      ),
      'mnuLoadEntryLog' => 
      array (
        'label' => 'Load Entry',
      ),
      'mnuAvailTrucks' => 
      array (
        'label' => 'Available Trucks/Loads',
      ),
      'mnuLoadInqLog' => 
      array (
        'label' => 'Load Inquiry',
      ),
      'mnuCreditCheckLog' => 
      array (
        'label' => 'Customer Credit Check',
      ),
      'mnuLookupLoadsLog' => 
      array (
        'label' => 'Lookup Loads by Location',
      ),
      'mnuBrokerTracking' => 
      array (
        'label' => 'Broker Tracking',
      ),
      'mnuCarrierMaint' => 
      array (
        'label' => 'Carrier Maintenance',
      ),
      'mnuCarrierHistory' => 
      array (
        'label' => 'Carrier History',
      ),
      'mnuPrintBrokerConf' => 
      array (
        'label' => 'Reprint Broker Confirmation',
      ),
    ),
  ),
  'imaging' => 
  array (
    'label' => 'Imaging',
    'icon' => 'bi-images',
    'items' => 
    array (
      'mnuImageAudit' => 
      array (
        'label' => 'Image Audit',
      ),
      'mnuImageExceptions' => 
      array (
        'label' => 'Image Exceptions',
      ),
    ),
  ),
  'reports' => 
  array (
    'label' => 'Reports',
    'icon' => 'bi-file-earmark-bar-graph',
    'items' => 
    array (
      'mnuPrintJobs' => 
      array (
        'label' => 'Print Jobs',
      ),
      'submit_reports' => 
      array (
        'label' => 'Submit Reports',
        'items' => 
        array (
          'mnuDriverMiles' => 
          array (
            'label' => 'Driver Miles',
          ),
          'mnuSubmitReportUnitMiles' => 
          array (
            'label' => 'Unit Miles',
          ),
        ),
      ),
      'mnuSubmitReport' => 
      array (
        'label' => 'Submit Report Job',
      ),
      'mnuAgingReport' => 
      array (
        'label' => 'Aging Report',
      ),
      'mnuCBAgingReport' => 
      array (
        'label' => 'C/B Aging Report',
      ),
      'mnuCustRevSum' => 
      array (
        'label' => 'Customer Revenue Summary',
      ),
      'mnuUnitRev' => 
      array (
        'label' => 'Unit Revenue Summary',
      ),
      'mnuUnitStateReport' => 
      array (
        'label' => 'Unit State Mileage Summary',
      ),
      'mnuReloadReport' => 
      array (
        'label' => 'Reload Report',
      ),
      'mnuTrafficLanes' => 
      array (
        'label' => 'Traffic Lanes',
      ),
      'mnuPosMap' => 
      array (
        'label' => 'Unit/Load Positions',
      ),
      'mnuOwnerLabels' => 
      array (
        'label' => 'Owner/Driver Labels',
      ),
      'mnuTeamDriverList' => 
      array (
        'label' => 'Team Driver List (Excel)',
      ),
      'mnuActiveDriverList' => 
      array (
        'label' => 'Active Driver Address Listing',
      ),
      'mnuActiveCarrierList' => 
      array (
        'label' => 'Active Carrier Address Listing',
      ),
      'mnuTractorList' => 
      array (
        'label' => 'Tractor Listing',
      ),
      'mnuTrailerList' => 
      array (
        'label' => 'Trailer Listing',
      ),
      'mnuDriverInsRpt' => 
      array (
        'label' => 'Driver Insurance Report',
      ),
      'mnuRecap' => 
      array (
        'label' => 'Weekly Recap',
      ),
      'mnuPrint1099' => 
      array (
        'label' => 'Print 1099',
      ),
      'mnuSMDRReport' => 
      array (
        'label' => 'SMDR Report',
      ),
    ),
  ),
  'safety' => 
  array (
    'label' => 'Safety',
    'icon' => 'bi-shield-check',
    'items' => 
    array (
      'mnuAgentMaint' => 
      array (
        'label' => 'Agent Maintenance',
      ),
      'mnuDriverMaint' => 
      array (
        'label' => 'Driver Maintenance',
      ),
      'mnuOwnerMaint' => 
      array (
        'label' => 'Owner Maintenance',
      ),
      'mnuUnitMaint' => 
      array (
        'label' => 'Unit Maintenance',
      ),
      'mnuTractorTrailer' => 
      array (
        'label' => 'Tractor/Trailer Lookup',
      ),
      'mnuDriverPictures' => 
      array (
        'label' => 'Load Driver Pictures',
      ),
      'mnuFuel' => 
      array (
        'label' => 'Fuel Receipts',
      ),
      'mnuEFSMoneyCode' => 
      array (
        'label' => 'Issue EFS Money Code (Paper Check)',
      ),
      'efs_maintenance' => 
      array (
        'label' => 'EFS Maintenance',
        'items' => 
        array (
          'mnuEFSPolicyMaintenance' => 
          array (
            'label' => 'EFS Policy Maintenance',
          ),
          'mnuEFSCardMaintenance' => 
          array (
            'label' => 'EFS Card Maintenance',
          ),
        ),
      ),
    ),
  ),
  'payroll' => 
  array (
    'label' => 'Payroll',
    'icon' => 'bi-clock',
    'items' => 
    array (
      'mnuTimeClockInOut' => 
      array (
        'label' => 'Clock In/Out',
      ),
      'separator_payroll1' => 
      array (
        'separator' => true,
      ),
      'owner_settlements' => 
      array (
        'label' => 'Unit Settlements',
        'items' => 
        array (
          'mnuPayrollMaint' => 
          array (
            'label' => 'Payroll Maintenance',
          ),
          'mnuUnitCheck' => 
          array (
            'label' => 'Unit Check Lookup',
          ),
          'separator_owner1' => 
          array (
            'separator' => true,
          ),
          'mnuCreateRepeating' => 
          array (
            'label' => 'Create Repeating Deductions',
          ),
          'mnuTrialOwnerPR' => 
          array (
            'label' => 'Run Trial Settlements',
          ),
          'mnuPreviewOwnerPR' => 
          array (
            'label' => 'Preview Settlements',
          ),
          'mnuFinalizeOwnerPR' => 
          array (
            'label' => 'Finalize Settlements',
          ),
          'mnuPrintOwnerPR' => 
          array (
            'label' => 'Print Checks',
          ),
          'mnuPrintOwnerDD' => 
          array (
            'label' => 'Print Direct Deposits',
          ),
        ),
      ),
      'agent_settlements' => 
      array (
        'label' => 'Agent Settlements',
        'items' => 
        array (
          'mnuAgentCheck' => 
          array (
            'label' => 'Agent Check Lookup',
          ),
          'separator_agent1' => 
          array (
            'separator' => true,
          ),
          'mnuTrialAgentPR' => 
          array (
            'label' => 'Run Trial Settlements',
          ),
          'mnuPreviewAgentPR' => 
          array (
            'label' => 'Preview Settlements',
          ),
          'mnuFinalizeAgentPR' => 
          array (
            'label' => 'Finalize Settlements',
          ),
          'mnuPrintAgentPR' => 
          array (
            'label' => 'Print Settlements',
          ),
        ),
      ),
      'carrier_settlements' => 
      array (
        'label' => 'Carrier Settlements',
        'items' => 
        array (
          'mnuCarrierCheck' => 
          array (
            'label' => 'Carrier Check Lookup',
          ),
          'separator_carrier2' => 
          array (
            'separator' => true,
          ),
          'mnuLogTrial' => 
          array (
            'label' => 'Run Trial Settlements',
          ),
          'mnuLogPreview' => 
          array (
            'label' => 'Preview Settlements',
          ),
          'mnuLogFinal' => 
          array (
            'label' => 'Finalize Settlements',
          ),
          'mnuLogPrint' => 
          array (
            'label' => 'Print Settlements',
          ),
          'mnuReassignCarrierLoad' => 
          array (
            'label' => 'Re-Assign Carrier Load',
          ),
        ),
      ),
    ),
  ),
  'system' => 
  array (
    'label' => 'Systems',
    'icon' => 'bi-gear',
    'items' => 
    array (
      'mnuProjectLog' => 
      array (
        'label' => 'Support Request',
      ),
      'mnuTLSBilling' => 
      array (
        'label' => 'TL Systems Billing',
      ),
      'mnuUserSecurity' => 
      array (
        'label' => 'User Security',
      ),
      'mnuEDIUserProfile' => 
      array (
        'label' => 'EDI User Profile',
      ),
      'development' => 
      array (
        'label' => 'Development',
        'items' => 
        array (
          'devTrailerTypeColor' => 
          array (
            'label' => 'Trailer Type Colors',
          ),
          'devExportMenu' => 
          array (
            'label' => 'Export Menu',
          ),
        ),
      ),
    ),
  ),
);
