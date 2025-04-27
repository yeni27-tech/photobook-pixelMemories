<?php
session_start();
header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'models/User.php';
require_once 'models/PhotoBook.php';
require_once 'models/Photo.php';
require_once 'models/Frame.php';
require_once 'utils/image_processor.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$userModel = new User($db);
$photobookModel = new PhotoBook($db);
$photoModel = new Photo($db);
$frameModel = new Frame($db);

switch ($action) {
    case 'register':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($userModel->register($data['username'], $data['email'], $data['password'])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Registration failed']);
            }
        }
        break;
        
    case 'login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = $userModel->login($data['email'], $data['password']);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            }
        }
        break;
        
    case 'create_photobook':
        if ($method === 'POST' && isset($_SESSION['user_id'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            $photobook_id = $photobookModel->create(
                $_SESSION['user_id'],
                $data['title'],
                $data['description'],
                $data['cover_image'] ?? null
            );
            echo json_encode(['success' => !!$photobook_id, 'photobook_id' => $photobook_id]);
        }
        break;
        
    case 'upload_photo':
        if ($method === 'POST' && isset($_SESSION['user_id'])) {
            $photobook_id = $_POST['photobook_id'];
            $caption = $_POST['caption'] ?? '';
            $frame_id = $_POST['frame_id'] ?? null;
            
            $target_dir = "uploads/photos/";
            $file_name = uniqid() . '_' . basename($_FILES["photo"]["name"]);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                $photoModel->upload($photobook_id, $target_file, $caption, $frame_id);
                echo json_encode(['success' => true, 'file_path' => $target_file]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Upload failed']);
            }
        }
        break;
        
    case 'get_photobooks':
        if ($method === 'GET' && isset($_SESSION['user_id'])) {
            $photobooks = $photobookModel->getAll($_SESSION['user_id']);
            echo json_encode(['success' => true, 'photobooks' => $photobooks]);
        }
        break;
        
    case 'get_photos':
        if ($method === 'GET' && isset($_SESSION['user_id'])) {
            $photobook_id = $_GET['photobook_id'];
            $photos = $photoModel->getByPhotoBookId($photobook_id);
            echo json_encode(['success' => true, 'photos' => $photos]);
        }
        break;
        
    case 'apply_frame':
        if ($method === 'POST' && isset($_SESSION['user_id'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            $new_path = ImageProcessor::applyFrame($data['photo_path'], $data['frame_path']);
            $photoModel->updateFrame($data['photo_id'], $data['frame_id']);
            echo json_encode(['success' => true, 'new_path' => $new_path]);
        }
        break;
        
    case 'apply_filter':
        if ($method === 'POST' && isset($_SESSION['user_id'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            $new_path = ImageProcessor::applyFilter($data['photo_path'], $data['filter_type'], $data['value']);
            echo json_encode(['success' => true, 'new_path' => $new_path]);
        }
        break;
        
    case 'download_photobook':
        if ($method === 'GET' && isset($_SESSION['user_id'])) {
            $photobook_id = $_GET['photobook_id'];
            $photos = $photoModel->getByPhotoBookId($photobook_id);
            $pdf_path = ImageProcessor::generatePDF($photobook_id, $photos);
            echo json_encode(['success' => true, 'pdf_path' => $pdf_path]);
        }
        break;
        
    case 'delete_photobook':
        if ($method === 'POST' && isset($_SESSION['user_id'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            $success = $photobookModel->delete($data['photobook_id'], $_SESSION['user_id']);
            echo json_encode(['success' => $success]);
        }
        break;
        
    case 'get_frames':
        if ($method === 'GET') {
            $frames = $frameModel->getAllFrames();
            echo json_encode(['success' => true, 'frames' => $frames]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>