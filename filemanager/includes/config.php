<?php
// filepath: filemanager/includes/config.php
// Configuration du gestionnaire de fichiers

// Taille maximale des fichiers (en octets)
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB

// Extensions autorisées
$allowed_extensions = [
    // Images
    'jpg',
    'jpeg',
    'png',
    'gif',

    // Documents
    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'txt',
    'csv',

    // Archives
    'zip'
];

// Types MIME autorisés
$allowed_mime_types = [
    // Images
    'image/jpeg',
    'image/png',
    'image/gif',

    // Documents
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'text/csv',

    // Archives
    'application/zip'
];

// Icônes pour les types de fichiers
$file_icons = [
    // Images
    'jpg' => 'fa-file-image',
    'jpeg' => 'fa-file-image',
    'png' => 'fa-file-image',
    'gif' => 'fa-file-image',

    // Documents
    'pdf' => 'fa-file-pdf',
    'doc' => 'fa-file-word',
    'docx' => 'fa-file-word',
    'xls' => 'fa-file-excel',
    'xlsx' => 'fa-file-excel',
    'txt' => 'fa-file-alt',
    'csv' => 'fa-file-csv',

    // Archives
    'zip' => 'fa-file-archive',

    // Par défaut
    'default' => 'fa-file'
];

// Chemin de stockage des fichiers
define('STORAGE_PATH', dirname(__DIR__, 2) . '/storage/users/');
