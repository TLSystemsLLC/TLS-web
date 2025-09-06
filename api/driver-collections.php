<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';

// Initialize authentication
$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'), 
    Config::get('DB_PASSWORD')
);

// Require authentication
$auth->requireAuth('/tls/login.php');

// Check menu permissions
if (!$auth->hasMenuAccess('mnuDriverMaint')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Initialize database connection
$db = new Database(Config::get('DB_SERVER'), Config::get('DB_USERNAME'), Config::get('DB_PASSWORD'));
$db->connect($auth->getCurrentUser()['customer_db']);

// Handle different actions
switch ($action) {
    // ADDRESS MANAGEMENT
    case 'get_addresses':
        $driverKey = intval($_GET['driver_key'] ?? 0);
        if ($driverKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid driver key']);
            exit;
        }
        
        try {
            // Call spDriverNameAddresses_Get to retrieve all addresses for driver
            $addresses = $db->executeStoredProcedure('spDriverNameAddresses_Get', [$driverKey]);
            echo json_encode(['success' => true, 'addresses' => $addresses]);
        } catch (Exception $e) {
            error_log('Error loading driver addresses: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error loading addresses']);
        }
        break;
        
    case 'get_address':
        $nameKey = intval($_GET['name_key'] ?? 0);
        if ($nameKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid name key']);
            exit;
        }
        
        try {
            // Get specific address details for editing
            $result = $db->executeStoredProcedure('spNameAddress_Get', [$nameKey]);
            $address = !empty($result) ? $result[0] : null;
            echo json_encode(['success' => true, 'address' => $address]);
        } catch (Exception $e) {
            error_log('Error loading address: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error loading address']);
        }
        break;
        
    case 'save_address':
        $driverKey = intval($_POST['driver_key'] ?? 0);
        $nameKey = intval($_POST['name_key'] ?? 0);
        
        if ($driverKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid driver key']);
            exit;
        }
        
        try {
            // First save the address using spNameAddress_Save
            $addressParams = [
                $nameKey, // NameKey (0 for new)
                $_POST['name1'] ?? '',
                $_POST['name2'] ?? '',
                $_POST['address1'] ?? '',
                $_POST['address2'] ?? '',
                $_POST['address3'] ?? '',
                $_POST['address4'] ?? '',
                $_POST['city'] ?? '',
                $_POST['state'] ?? '',
                $_POST['zip'] ?? '',
                '', // County (not in form)
                $_POST['phone'] ?? '',
                '', // Fax (not in form)
                $_POST['quickkey'] ?? '',
                'DR', // NameQual for driver addresses
                1 // Active
            ];
            
            // spNameAddress_Save returns the NameKey
            $result = $db->executeStoredProcedureWithReturn('spNameAddress_Save', $addressParams);
            
            if ($result && isset($result['return_value'])) {
                $savedNameKey = $result['return_value'];
                
                // Now link the address to the driver using spDriverNameAddresses_Save
                $linkParams = [$driverKey, $savedNameKey];
                $db->executeStoredProcedure('spDriverNameAddresses_Save', $linkParams);
                
                echo json_encode(['success' => true, 'message' => 'Address saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error saving address']);
            }
        } catch (Exception $e) {
            error_log('Error saving address: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error saving address: ' . $e->getMessage()]);
        }
        break;
        
    case 'delete_address':
        $driverKey = intval($_POST['driver_key'] ?? 0);
        $nameKey = intval($_POST['name_key'] ?? 0);
        
        if ($driverKey <= 0 || $nameKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        try {
            // Remove the link between driver and address
            $db->executeStoredProcedure('spDriverNameAddresses_Delete', [$driverKey, $nameKey]);
            
            // Optionally delete the address itself if not used elsewhere
            // $db->executeStoredProcedure('spNameAddress_Delete', [$nameKey]);
            
            echo json_encode(['success' => true, 'message' => 'Address deleted successfully']);
        } catch (Exception $e) {
            error_log('Error deleting address: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error deleting address']);
        }
        break;
        
    // CONTACT MANAGEMENT
    case 'get_contacts':
        $driverKey = intval($_GET['driver_key'] ?? 0);
        if ($driverKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid driver key']);
            exit;
        }
        
        try {
            // Call spContact_Get to retrieve all contacts for driver
            $contacts = $db->executeStoredProcedure('spContact_Get', [$driverKey]);
            echo json_encode(['success' => true, 'contacts' => $contacts]);
        } catch (Exception $e) {
            error_log('Error loading driver contacts: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error loading contacts']);
        }
        break;
        
    case 'get_contact':
        $contactKey = intval($_GET['contact_key'] ?? 0);
        if ($contactKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid contact key']);
            exit;
        }
        
        try {
            // Get specific contact details for editing
            $result = $db->executeStoredProcedure('spContact_Get', [0, $contactKey]);
            $contact = !empty($result) ? $result[0] : null;
            echo json_encode(['success' => true, 'contact' => $contact]);
        } catch (Exception $e) {
            error_log('Error loading contact: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error loading contact']);
        }
        break;
        
    case 'save_contact':
        $driverKey = intval($_POST['driver_key'] ?? 0);
        $contactKey = intval($_POST['contact_key'] ?? 0);
        
        if ($driverKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid driver key']);
            exit;
        }
        
        try {
            // Save contact using spContact_Save
            $contactParams = [
                $contactKey, // ContactKey (0 for new)
                $driverKey, // Link to driver
                $_POST['first_name'] ?? '',
                $_POST['last_name'] ?? '',
                $_POST['phone'] ?? '',
                $_POST['mobile'] ?? '',
                $_POST['email'] ?? '',
                $_POST['relationship'] ?? '',
                isset($_POST['is_primary']) ? 1 : 0
            ];
            
            $result = $db->executeStoredProcedure('spContact_Save', $contactParams);
            echo json_encode(['success' => true, 'message' => 'Contact saved successfully']);
        } catch (Exception $e) {
            error_log('Error saving contact: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error saving contact: ' . $e->getMessage()]);
        }
        break;
        
    case 'delete_contact':
        $contactKey = intval($_POST['contact_key'] ?? 0);
        
        if ($contactKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid contact key']);
            exit;
        }
        
        try {
            // Delete contact
            $db->executeStoredProcedure('spContact_Delete', [$contactKey]);
            echo json_encode(['success' => true, 'message' => 'Contact deleted successfully']);
        } catch (Exception $e) {
            error_log('Error deleting contact: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error deleting contact']);
        }
        break;
        
    // COMMENT MANAGEMENT
    case 'get_comments':
        $driverKey = intval($_GET['driver_key'] ?? 0);
        if ($driverKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid driver key']);
            exit;
        }
        
        try {
            // Call spComment_Get to retrieve all comments for driver
            $comments = $db->executeStoredProcedure('spComment_Get', [$driverKey]);
            echo json_encode(['success' => true, 'comments' => $comments]);
        } catch (Exception $e) {
            error_log('Error loading driver comments: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error loading comments']);
        }
        break;
        
    case 'save_comment':
        $driverKey = intval($_POST['driver_key'] ?? 0);
        
        if ($driverKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid driver key']);
            exit;
        }
        
        try {
            // Save comment using spComment_Save
            $currentUser = $auth->getCurrentUser()['userid'];
            $commentParams = [
                0, // CommentKey (0 for new)
                $driverKey, // Link to driver
                date('Y-m-d H:i:s'), // CommentDate
                $currentUser, // CommentBy
                $_POST['comment_text'] ?? '',
                $_POST['comment_type'] ?? 'General'
            ];
            
            $result = $db->executeStoredProcedure('spComment_Save', $commentParams);
            
            // Link comment to driver using spDriverComments_Save if needed
            if ($result) {
                $db->executeStoredProcedure('spDriverComments_Save', [$driverKey, $result['CommentKey'] ?? 0]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Comment saved successfully']);
        } catch (Exception $e) {
            error_log('Error saving comment: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error saving comment: ' . $e->getMessage()]);
        }
        break;
        
    case 'delete_comment':
        $commentKey = intval($_POST['comment_key'] ?? 0);
        
        if ($commentKey <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid comment key']);
            exit;
        }
        
        try {
            // Delete comment
            $db->executeStoredProcedure('spComment_Delete', [$commentKey]);
            echo json_encode(['success' => true, 'message' => 'Comment deleted successfully']);
        } catch (Exception $e) {
            error_log('Error deleting comment: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error deleting comment']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// Close database connection
$db->disconnect();