<?php
// Missing library dashboard functions

function get_library_resources_for_user(int $userId, bool $approvedOnly = true, int $limit = 6): array {
    $user = find_user_by_id($userId);
    $courseYear = trim($user['course_year'] ?? '');
    if ($courseYear === '') {
        return [];
    }
    $pdo = get_db();
    if ($approvedOnly) {
        // Only show resources for the user's course
        $stmt = $pdo->prepare('SELECT lr.*, u.full_name as uploader_name FROM library_resources lr LEFT JOIN users u ON lr.uploaded_by = u.id WHERE lr.is_approved = 1 AND lr.course_year = :course_year ORDER BY lr.created_at DESC LIMIT :limit');
    } else {
        $stmt = $pdo->prepare('SELECT lr.*, u.full_name as uploader_name FROM library_resources lr LEFT JOIN users u ON lr.uploaded_by = u.id WHERE lr.course_year = :course_year ORDER BY lr.created_at DESC LIMIT :limit');
    }
    $stmt->bindValue(':course_year', $courseYear, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_open_access_resource_categories(string $courseYear = ''): array {
    $pdo = get_db();
    // Filter by course: show resources for the student's course OR general resources (NULL)
    $stmt = $pdo->prepare('SELECT DISTINCT category FROM library_open_access_resources WHERE is_active = 1 AND category IS NOT NULL AND (course_year = :course_year OR course_year IS NULL) ORDER BY category ASC');
    $stmt->bindValue(':course_year', $courseYear, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function get_open_access_resources(string $courseYear = '', int $limit = 0): array {
    $pdo = get_db();
    // Filter by course: show resources for the student's course OR general resources (NULL)
    $sql = 'SELECT lor.*, u.full_name as creator_name FROM library_open_access_resources lor LEFT JOIN users u ON lor.created_by = u.id WHERE lor.is_active = 1 AND (lor.course_year = :course_year OR lor.course_year IS NULL) ORDER BY lor.created_at DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT :limit';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':course_year', $courseYear, PDO::PARAM_STR);
    if ($limit > 0) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_user_course_category(int $userId): ?string {
    $user = find_user_by_id($userId);
    if (!$user) {
        return null;
    }
    return $user['course_year'] ?? $user['course'] ?? null;
}

function save_library_feedback(array $data): bool {
    $pdo = get_db();
    $lookup = $pdo->prepare(
        'SELECT id FROM library_feedback WHERE borrow_id = :borrow_id AND user_id = :user_id LIMIT 1'
    );
    $lookup->execute([
        'borrow_id' => (int) $data['borrow_id'],
        'user_id' => (int) $data['user_id'],
    ]);
    $feedbackId = $lookup->fetchColumn();

    $values = [
        'borrow_id' => (int) $data['borrow_id'],
        'user_id' => (int) $data['user_id'],
        'book_id' => (int) $data['book_id'],
        'rating' => (int) $data['book_rating'],
        'book_rating' => (int) $data['book_rating'],
        'service_rating' => (int) $data['service_rating'],
        'book_review' => trim((string) ($data['book_review'] ?? '')),
        'service_feedback' => trim((string) ($data['service_feedback'] ?? '')),
    ];

    if ($feedbackId) {
        $stmt = $pdo->prepare(
            'UPDATE library_feedback
             SET book_rating = :book_rating, service_rating = :service_rating,
                 rating = :rating, book_review = :book_review, service_feedback = :service_feedback
             WHERE id = :id'
        );
        $values['id'] = (int) $feedbackId;
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO library_feedback
             (borrow_id, user_id, book_id, rating, book_rating, service_rating, book_review, service_feedback, created_at)
             VALUES (:borrow_id, :user_id, :book_id, :rating, :book_rating, :service_rating, :book_review, :service_feedback, NOW())'
        );
    }

    return $stmt->execute($values);
}
