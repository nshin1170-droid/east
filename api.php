<?php
/**
 * East Systems Colleges of Rizal - Quiz App
 * Backend REST API — MySQL Version
 * For deployment on InfinityFree / 000webhost / any PHP+MySQL host
 *
 * SETUP INSTRUCTIONS (after uploading to InfinityFree):
 *  1. Create a MySQL database in your InfinityFree control panel.
 *  2. Import database.sql into that database (via phpMyAdmin).
 *  3. Fill in DB_HOST, DB_NAME, DB_USER, DB_PASS below with your credentials.
 *  4. Upload this file as api.php to your hosting folder.
 */

// ============================================================
// ★ CONFIGURATION — Fill these in with your InfinityFree MySQL details
// ============================================================
define('DB_HOST', 'sql312.infinityfree.com');
define('DB_NAME', 'if0_43008540_quiz_app');
define('DB_USER', 'if0_43008540');
define('DB_PASS', 'KH8PwTh5FF8'); // ← ILAGAY MO ANG PASSWORD MO DITO
// ============================================================

// Allow CORS for cross-origin / WebIntoApp requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// MySQL Database Connection via PDO
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Parse input
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$inputData = [];
$rawInput = file_get_contents('php://input');
if ($rawInput) {
    $json = json_decode($rawInput, true);
    if (is_array($json)) {
        $inputData = $json;
    }
}
$data = array_merge($_POST, $inputData);

// Handle Actions
try {
    switch ($action) {
        case 'ping':
            echo json_encode(['success' => true, 'message' => 'API is active and connected to MySQL database.', 'timestamp' => date('Y-m-d H:i:s')]);
            break;
        case 'login':            handleLogin($db, $data); break;
        case 'signup':           handleSignup($db, $data); break;
        case 'get_questions':    handleGetQuestions($db, $_GET); break;
        case 'add_question':     handleAddQuestion($db, $data); break;
        case 'edit_question':    handleEditQuestion($db, $data); break;
        case 'delete_question':  handleDeleteQuestion($db, $data); break;
        case 'get_leaderboard':  handleGetLeaderboard($db); break;
        case 'save_score':       handleSaveScore($db, $data); break;
        case 'get_user_progress':   handleGetUserProgress($db, $_GET); break;
        case 'save_user_progress':  handleSaveUserProgress($db, $data); break;
        case 'get_admin_stats':     handleGetAdminStats($db); break;
        case 'get_student_records': handleGetStudentRecords($db); break;
        case 'get_quiz_history':    handleGetQuizHistory($db); break;
        case 'delete_score':        handleDeleteScore($db, $data); break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . htmlspecialchars($action)]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ======================== FUNCTION IMPLEMENTATIONS ======================== //

function handleLogin($db, $data) {
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    $role = trim($data['role'] ?? '');

    if (!$username || !$password) {
        echo json_encode(['success' => false, 'error' => 'Please provide both username and password.']);
        return;
    }

    $stmt = $db->prepare("SELECT id, name, username, password, role FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user || $user['password'] !== $password) {
        echo json_encode(['success' => false, 'error' => 'Invalid username or password.']);
        return;
    }

    if ($role && $user['role'] !== $role) {
        echo json_encode(['success' => false, 'error' => 'Account exists but is not registered as ' . ucfirst($role) . '.']);
        return;
    }

    unset($user['password']);
    echo json_encode(['success' => true, 'message' => 'Login successful!', 'user' => $user]);
}

function handleSignup($db, $data) {
    $name     = trim($data['name'] ?? '');
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    $role     = trim($data['role'] ?? 'student');

    if (!$name || !$username || !$password) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        return;
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Username is already taken. Please choose another.']);
        return;
    }

    $stmt = $db->prepare("INSERT INTO users (name, username, password, role) VALUES (:n, :u, :p, :r)");
    $stmt->execute([':n' => $name, ':u' => $username, ':p' => $password, ':r' => $role]);
    $userId = $db->lastInsertId();

    if ($role === 'student') {
        $stmt = $db->prepare("INSERT IGNORE INTO user_progress (user_id, powerups_json, badges_json, subject_stats_json) VALUES (:uid, :pu, :bdg, :subj)");
        $stmt->execute([
            ':uid'  => $userId,
            ':pu'   => json_encode(['p50' => 3, 'skip' => 2, 'hint' => 2, 'sec' => 1]),
            ':bdg'  => json_encode([]),
            ':subj' => json_encode(new stdClass())
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Account created successfully! You can now log in.', 'user_id' => $userId]);
}

function handleGetQuestions($db, $params) {
    $grade      = $params['grade'] ?? 'all';
    $category   = $params['category'] ?? 'all';
    $difficulty = $params['difficulty'] ?? 'all';
    $type       = $params['type'] ?? 'all';

    $sql = "SELECT * FROM questions WHERE 1=1";
    $bindings = [];

    if ($grade !== 'all' && $grade !== '') {
        $sql .= " AND grade = :grade";
        $bindings[':grade'] = $grade;
    }
    if ($category !== 'all' && $category !== '') {
        $sql .= " AND category = :cat";
        $bindings[':cat'] = $category;
    }
    if ($difficulty !== 'all' && $difficulty !== '') {
        $sql .= " AND difficulty = :diff";
        $bindings[':diff'] = $difficulty;
    }
    if ($type !== 'all' && $type !== '') {
        $sql .= " AND type = :t";
        $bindings[':t'] = $type;
    }

    $sql .= " ORDER BY id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($bindings);
    $rows = $stmt->fetchAll();

    $questions = [];
    foreach ($rows as $row) {
        $choices = json_decode($row['choices'], true);
        if (!is_array($choices)) $choices = [];
        $qType = $row['type'] ?: 'multiple_choice';
        $questions[] = [
            'id'          => (int)$row['id'],
            'grade'       => $row['grade'],
            'category'    => $row['category'],
            'difficulty'  => $row['difficulty'],
            'type'        => $qType,
            'question'    => $row['question'],
            'choices'     => $choices,
            'correct'     => (int)$row['correct'],
            'correct_text'=> $row['correct_text'] ?? '',
            'explanation' => $row['explanation'] ?? ''
        ];
    }

    echo json_encode(['success' => true, 'questions' => $questions]);
}

function handleAddQuestion($db, $data) {
    $grade      = trim($data['grade'] ?? '11');
    $category   = trim($data['category'] ?? 'Science');
    $difficulty = trim($data['difficulty'] ?? 'Easy');
    $type       = trim($data['type'] ?? 'multiple_choice');
    $question   = trim($data['question'] ?? '');
    $choices    = $data['choices'] ?? [];
    $correct    = (int)($data['correct'] ?? 0);
    $correctText = trim($data['correct_text'] ?? '');
    $explanation = trim($data['explanation'] ?? '');

    if (!$question) {
        echo json_encode(['success' => false, 'error' => 'Please provide question text.']);
        return;
    }

    if ($type === 'multiple_choice' && count($choices) < 4) {
        echo json_encode(['success' => false, 'error' => 'Multiple choice requires all 4 options (A, B, C, D).']);
        return;
    } elseif ($type === 'true_false') {
        $choices = ['True', 'False'];
    } elseif ($type === 'identification') {
        $choices = [];
        if (!$correctText) {
            echo json_encode(['success' => false, 'error' => 'Please provide the correct answer for identification questions.']);
            return;
        }
    }

    $stmt = $db->prepare("INSERT INTO questions (grade, category, difficulty, type, question, choices, correct, correct_text, explanation) VALUES (:g, :c, :d, :t, :q, :ch, :cor, :ctx, :exp)");
    $stmt->execute([
        ':g'   => $grade, ':c' => $category, ':d' => $difficulty, ':t' => $type,
        ':q'   => $question, ':ch' => json_encode($choices), ':cor' => $correct,
        ':ctx' => $correctText, ':exp' => $explanation ?: 'The correct answer is shown above.'
    ]);

    echo json_encode(['success' => true, 'message' => 'Question added!', 'id' => $db->lastInsertId()]);
}

function handleEditQuestion($db, $data) {
    $id         = (int)($data['id'] ?? 0);
    $grade      = trim($data['grade'] ?? '11');
    $category   = trim($data['category'] ?? 'Science');
    $difficulty = trim($data['difficulty'] ?? 'Easy');
    $type       = trim($data['type'] ?? 'multiple_choice');
    $question   = trim($data['question'] ?? '');
    $choices    = $data['choices'] ?? [];
    $correct    = (int)($data['correct'] ?? 0);
    $correctText = trim($data['correct_text'] ?? '');
    $explanation = trim($data['explanation'] ?? '');

    if (!$id || !$question) {
        echo json_encode(['success' => false, 'error' => 'Invalid question data or ID.']);
        return;
    }

    if ($type === 'true_false')      $choices = ['True', 'False'];
    elseif ($type === 'identification') $choices = [];

    $stmt = $db->prepare("UPDATE questions SET grade=:g, category=:c, difficulty=:d, type=:t, question=:q, choices=:ch, correct=:cor, correct_text=:ctx, explanation=:exp WHERE id=:id");
    $stmt->execute([
        ':g' => $grade, ':c' => $category, ':d' => $difficulty, ':t' => $type,
        ':q' => $question, ':ch' => json_encode($choices), ':cor' => $correct,
        ':ctx' => $correctText, ':exp' => $explanation, ':id' => $id
    ]);

    echo json_encode(['success' => true, 'message' => 'Question updated!']);
}

function handleDeleteQuestion($db, $data) {
    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid question ID.']); return; }
    $stmt = $db->prepare("DELETE FROM questions WHERE id = :id");
    $stmt->execute([':id' => $id]);
    echo json_encode(['success' => true, 'message' => 'Question deleted.']);
}

function handleSaveScore($db, $data) {
    $userId      = (int)($data['user_id'] ?? 0);
    $studentName = trim($data['student_name'] ?? 'Student');
    $grade       = trim($data['grade'] ?? '11');
    $subject     = trim($data['subject'] ?? 'General');
    $difficulty  = trim($data['difficulty'] ?? 'All');
    $score       = (int)($data['score'] ?? 0);
    $total       = (int)($data['total_questions'] ?? 1);
    $percentage  = round(($score / max(1, $total)) * 100);
    $points      = (int)($data['points_earned'] ?? ($score * 10));

    $stmt = $db->prepare("INSERT INTO scores (user_id, student_name, grade, subject, difficulty, score, total_questions, percentage, points_earned) VALUES (:uid, :n, :g, :s, :d, :sc, :tot, :pct, :pts)");
    $stmt->execute([
        ':uid' => $userId ?: null, ':n' => $studentName, ':g' => $grade,
        ':s' => $subject, ':d' => $difficulty, ':sc' => $score,
        ':tot' => $total, ':pct' => $percentage, ':pts' => $points
    ]);

    echo json_encode(['success' => true, 'message' => 'Score recorded!', 'id' => $db->lastInsertId()]);
}

function handleGetLeaderboard($db) {
    // Show ALL registered students ranked by total points (shared across all WiFi/devices)
    $stmt = $db->query("
        SELECT
            u.id,
            u.name,
            u.username,
            u.created_at,
            COALESCE(p.points, 0) AS total_points,
            COALESCE(p.level, 1) AS level,
            COALESCE(p.total_qs, 0) AS total_qs,
            COALESCE(p.correct_qs, 0) AS correct_qs,
            COALESCE(p.best_streak, 0) AS best_streak,
            COALESCE(p.badges_json, '[]') AS badges_json,
            (SELECT COUNT(*) FROM scores s WHERE s.user_id = u.id) AS quizzes_taken,
            (SELECT MAX(percentage) FROM scores s WHERE s.user_id = u.id) AS best_pct
        FROM users u
        LEFT JOIN user_progress p ON u.id = p.user_id
        WHERE u.role = 'student'
        ORDER BY total_points DESC, correct_qs DESC, u.id ASC
    ");
    $rows = $stmt->fetchAll();

    $results = [];
    foreach ($rows as $r) {
        $accuracy   = ($r['total_qs'] > 0) ? round(($r['correct_qs'] / $r['total_qs']) * 100) : 0;
        $badgeCount = count(json_decode($r['badges_json'] ?? '[]', true) ?: []);
        $results[]  = [
            'id'           => (int)$r['id'],
            'name'         => $r['name'],
            'username'     => $r['username'],
            'total_points' => (int)$r['total_points'],
            'level'        => (int)$r['level'],
            'quizzes'      => (int)$r['quizzes_taken'],
            'accuracy'     => $accuracy,
            'best_pct'     => (int)($r['best_pct'] ?? 0),
            'best_streak'  => (int)$r['best_streak'],
            'badges'       => $badgeCount,
            'joined'       => date('M d, Y', strtotime($r['created_at']))
        ];
    }

    echo json_encode(['success' => true, 'leaderboard' => $results]);
}

function handleGetUserProgress($db, $params) {
    $userId   = (int)($params['user_id'] ?? 0);
    $username = trim($params['username'] ?? '');

    if (!$userId && $username) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :u");
        $stmt->execute([':u' => $username]);
        $userId = (int)$stmt->fetchColumn();
    }

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'User ID required.']);
        return;
    }

    $stmt = $db->prepare("SELECT * FROM user_progress WHERE user_id = :uid");
    $stmt->execute([':uid' => $userId]);
    $progress = $stmt->fetch();

    if (!$progress) {
        $progress = [
            'user_id' => $userId, 'points' => 0, 'level' => 1, 'lives' => 5, 'max_lives' => 5,
            'streak' => 0, 'best_streak' => 0, 'total_qs' => 0, 'correct_qs' => 0,
            'powerups_json' => json_encode(['p50' => 3, 'skip' => 2, 'hint' => 2, 'sec' => 1]),
            'badges_json' => json_encode([]),
            'subject_stats_json' => json_encode(new stdClass()),
            'daily_done_date' => null
        ];
    }

    $pu     = json_decode($progress['powerups_json'] ?? '', true) ?: ['p50' => 3, 'skip' => 2, 'hint' => 2, 'sec' => 1];
    $badges = json_decode($progress['badges_json'] ?? '', true) ?: [];
    $subj   = json_decode($progress['subject_stats_json'] ?? '', true) ?: [];

    echo json_encode([
        'success'  => true,
        'progress' => [
            'points'     => (int)$progress['points'],
            'level'      => (int)$progress['level'],
            'lives'      => (int)$progress['lives'],
            'maxLives'   => (int)$progress['max_lives'],
            'streak'     => (int)$progress['streak'],
            'bestStreak' => (int)$progress['best_streak'],
            'totalQs'    => (int)$progress['total_qs'],
            'correctQs'  => (int)$progress['correct_qs'],
            'pu'         => $pu,
            'badges'     => $badges,
            'subj'       => $subj,
            'dailyDone'  => $progress['daily_done_date']
        ]
    ]);
}

function handleSaveUserProgress($db, $data) {
    $userId   = (int)($data['user_id'] ?? 0);
    $username = trim($data['username'] ?? '');

    if (!$userId && $username) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :u");
        $stmt->execute([':u' => $username]);
        $userId = (int)$stmt->fetchColumn();
    }

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'User ID required.']);
        return;
    }

    $p          = $data['progress'] ?? $data;
    $points     = (int)($p['points'] ?? 0);
    $level      = (int)($p['level'] ?? (floor($points / 100) + 1));
    $lives      = (int)($p['lives'] ?? 5);
    $maxLives   = (int)($p['maxLives'] ?? 5);
    $streak     = (int)($p['streak'] ?? 0);
    $bestStreak = (int)($p['bestStreak'] ?? 0);
    $totalQs    = (int)($p['totalQs'] ?? 0);
    $correctQs  = (int)($p['correctQs'] ?? 0);
    $pu         = json_encode($p['pu'] ?? ['p50' => 3, 'skip' => 2, 'hint' => 2, 'sec' => 1]);
    $badges     = json_encode($p['badges'] ?? []);
    $subj       = json_encode($p['subj'] ?? new stdClass());
    $dailyDone  = $p['dailyDone'] ?? null;

    // MySQL uses INSERT ... ON DUPLICATE KEY UPDATE
    $stmt = $db->prepare("
        INSERT INTO user_progress
            (user_id, points, level, lives, max_lives, streak, best_streak, total_qs, correct_qs, powerups_json, badges_json, subject_stats_json, daily_done_date, updated_at)
        VALUES
            (:uid, :pts, :lvl, :liv, :mliv, :str, :bstr, :tq, :cq, :pu, :bdg, :subj, :dd, NOW())
        ON DUPLICATE KEY UPDATE
            points=:pts, level=:lvl, lives=:liv, max_lives=:mliv, streak=:str, best_streak=:bstr,
            total_qs=:tq, correct_qs=:cq, powerups_json=:pu, badges_json=:bdg,
            subject_stats_json=:subj, daily_done_date=:dd, updated_at=NOW()
    ");

    $stmt->execute([
        ':uid' => $userId, ':pts' => $points, ':lvl' => $level, ':liv' => $lives,
        ':mliv' => $maxLives, ':str' => $streak, ':bstr' => $bestStreak,
        ':tq' => $totalQs, ':cq' => $correctQs, ':pu' => $pu,
        ':bdg' => $badges, ':subj' => $subj, ':dd' => $dailyDone
    ]);

    echo json_encode(['success' => true, 'message' => 'Progress saved.']);
}

function handleGetAdminStats($db) {
    $totalStudents  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $totalQuestions = (int)$db->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    $totalQuizzes   = (int)$db->query("SELECT COUNT(*) FROM scores")->fetchColumn();
    $avgAccuracy    = (float)$db->query("SELECT COALESCE(AVG(percentage), 0) FROM scores")->fetchColumn();

    $subjCounts  = $db->query("SELECT category, COUNT(*) as cnt FROM questions GROUP BY category")->fetchAll();
    $gradeCounts = $db->query("SELECT grade, COUNT(*) as cnt FROM questions GROUP BY grade")->fetchAll();
    $typeCounts  = $db->query("SELECT type, COUNT(*) as cnt FROM questions GROUP BY type")->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'totalStudents'  => $totalStudents,
            'totalQuestions' => $totalQuestions,
            'totalQuizzes'   => $totalQuizzes,
            'avgAccuracy'    => round($avgAccuracy, 1),
            'subjCounts'     => $subjCounts,
            'gradeCounts'    => $gradeCounts,
            'typeCounts'     => $typeCounts
        ]
    ]);
}

function handleGetStudentRecords($db) {
    $stmt = $db->query("
        SELECT u.id, u.name, u.username, u.created_at,
            COALESCE(p.points, 0) as points,
            COALESCE(p.level, 1) as level,
            COALESCE(p.total_qs, 0) as total_qs,
            COALESCE(p.correct_qs, 0) as correct_qs,
            (SELECT COUNT(*) FROM scores s WHERE s.user_id = u.id) as quizzes_taken,
            (SELECT MAX(percentage) FROM scores s WHERE s.user_id = u.id) as best_pct
        FROM users u
        LEFT JOIN user_progress p ON u.id = p.user_id
        WHERE u.role = 'student'
        ORDER BY points DESC, u.id DESC
    ");
    echo json_encode(['success' => true, 'students' => $stmt->fetchAll()]);
}

function handleGetQuizHistory($db) {
    $stmt = $db->query("SELECT id, user_id, student_name, grade, subject, difficulty, score, total_questions, percentage, points_earned, created_at FROM scores ORDER BY id DESC LIMIT 100");
    echo json_encode(['success' => true, 'history' => $stmt->fetchAll()]);
}

function handleDeleteScore($db, $data) {
    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid score ID.']); return; }
    $stmt = $db->prepare("DELETE FROM scores WHERE id = :id");
    $stmt->execute([':id' => $id]);
    echo json_encode(['success' => true, 'message' => 'Score record deleted.']);
}
