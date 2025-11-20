<?php
class KycDocument extends AppModel
{
  public $useTable = "kyc_documents";

  public $validate = array(
    'user_id' => array(
      'required' => array(
        'rule' => 'notEmpty',
        'message' => 'User ID is required'
      )
    ),
    'document_type' => array(
      'required' => array(
        'rule' => 'notEmpty',
        'message' => 'Document type is required'
      )
    ),
    'document_path' => array(
      'required' => array(
        'rule' => 'notEmpty',
        'message' => 'Document path is required'
      )
    )
  );

  // Document types for KYC (Indian-specific)
  public $documentTypes = array(
    'aadhaar_card' => 'Aadhaar Card',
    'pan_card' => 'PAN Card',
    'passport' => 'Passport',
    'voter_id' => 'Voter ID Card',
    'driving_license' => 'Driving License',
    'rera_certificate' => 'RERA Certificate',
    'gst_certificate' => 'GST Certificate',
    'company_registration' => 'Company Registration Certificate',
    'bank_statement' => 'Bank Statement',
    'utility_bill' => 'Utility Bill (Electricity/Gas)',
    'property_tax_receipt' => 'Property Tax Receipt',
    'sale_deed' => 'Sale Deed/Property Document'
  );

  // Belongs to User
  public $belongsTo = array(
    'User' => array(
      'className' => 'User',
      'foreignKey' => 'user_id'
    )
  );
}
?>
