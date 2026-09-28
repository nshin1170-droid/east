<?php
/**
 * East Systems Colleges of Rizal - Quiz App
 * Backend REST API & Database Controller
 * Uses PHP PDO with SQLite (and compatible with MySQL)
 */

// Start a server-side session so admin status is verified here, not just
// trusted from localStorage on the frontend.
session_start();

// Allow CORS for cross-origin or local requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database Connection via SQLite
$dbFile = __DIR__ . '/quiz_app.db';

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Initialize tables if they do not exist
initDatabase($db);

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
            echo json_encode(['success' => true, 'message' => 'API is active and connected to database.', 'timestamp' => date('Y-m-d H:i:s')]);
            break;

        case 'login':
            handleLogin($db, $data);
            break;

        case 'signup':
            handleSignup($db, $data);
            break;

        case 'logout':
            $_SESSION = [];
            session_destroy();
            echo json_encode(['success' => true]);
            break;

        case 'get_questions':
            handleGetQuestions($db, $_GET);
            break;

        case 'add_question':
            requireAdmin();
            handleAddQuestion($db, $data);
            break;

        case 'edit_question':
            requireAdmin();
            handleEditQuestion($db, $data);
            break;

        case 'delete_question':
            requireAdmin();
            handleDeleteQuestion($db, $data);
            break;

        case 'get_leaderboard':
            handleGetLeaderboard($db);
            break;

        case 'save_score':
            handleSaveScore($db, $data);
            break;

        case 'get_user_progress':
            handleGetUserProgress($db, $_GET);
            break;

        case 'save_user_progress':
            handleSaveUserProgress($db, $data);
            break;

        case 'get_admin_stats':
            requireAdmin();
            handleGetAdminStats($db);
            break;

        case 'get_student_records':
            requireAdmin();
            handleGetStudentRecords($db);
            break;

        case 'get_quiz_history':
            requireAdmin();
            handleGetQuizHistory($db);
            break;

        case 'delete_score':
            requireAdmin();
            handleDeleteScore($db, $data);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . htmlspecialchars($action)]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ======================== AUTH GUARD ======================== //

/**
 * Blocks the request unless the current PHP session belongs to a
 * logged-in admin. Call this at the top of any admin-only action.
 */
function requireAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin login required for this action.']);
        exit;
    }
}

// ======================== FUNCTION IMPLEMENTATIONS ======================== //

function initDatabase($db) {
    // 1. Users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'student',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Questions table
    $db->exec("CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        grade TEXT NOT NULL DEFAULT '11',
        category TEXT NOT NULL,
        difficulty TEXT NOT NULL DEFAULT 'Easy',
        type TEXT NOT NULL DEFAULT 'multiple_choice',
        question TEXT NOT NULL,
        choices TEXT NOT NULL,
        correct INTEGER NOT NULL DEFAULT 0,
        correct_text TEXT DEFAULT '',
        explanation TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Auto-migrate columns if missing
    $cols = $db->query("PRAGMA table_info(questions)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('type', $cols)) {
        $db->exec("ALTER TABLE questions ADD COLUMN type TEXT DEFAULT 'multiple_choice'");
    }
    if (!in_array('correct_text', $cols)) {
        $db->exec("ALTER TABLE questions ADD COLUMN correct_text TEXT DEFAULT ''");
    }

    // 3. Quiz Scores table (history of all quizzes)
    $db->exec("CREATE TABLE IF NOT EXISTS scores (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        student_name TEXT NOT NULL,
        grade TEXT DEFAULT '11',
        subject TEXT DEFAULT 'General',
        difficulty TEXT DEFAULT 'All',
        score INTEGER NOT NULL,
        total_questions INTEGER NOT NULL,
        percentage INTEGER NOT NULL,
        points_earned INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. User Progress table
    $db->exec("CREATE TABLE IF NOT EXISTS user_progress (
        user_id INTEGER PRIMARY KEY,
        points INTEGER DEFAULT 0,
        level INTEGER DEFAULT 1,
        lives INTEGER DEFAULT 5,
        max_lives INTEGER DEFAULT 5,
        streak INTEGER DEFAULT 0,
        best_streak INTEGER DEFAULT 0,
        total_qs INTEGER DEFAULT 0,
        correct_qs INTEGER DEFAULT 0,
        powerups_json TEXT,
        badges_json TEXT,
        subject_stats_json TEXT,
        daily_done_date TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default admin if missing
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO users (name, username, password, role) VALUES (:name, :username, :password, :role)");
        $stmt->execute([
            ':name' => 'Administrator',
            ':username' => 'admin',
            ':password' => 'admin123',
            ':role' => 'admin'
        ]);
    }

    // Seed demo student if missing
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'student'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO users (name, username, password, role) VALUES (:name, :username, :password, :role)");
        $stmt->execute([
            ':name' => 'Juan Dela Cruz',
            ':username' => 'student',
            ':password' => 'student123',
            ':role' => 'student'
        ]);
    }

    // Re-seed rich question bank if less than 70 questions exist
    $stmt = $db->prepare("SELECT COUNT(*) FROM questions");
    $stmt->execute();
    $cnt = (int)$stmt->fetchColumn();
    if ($cnt < 70) {
        $db->exec("DELETE FROM questions");
        seedQuestions($db);
    }
}

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

    // Record the verified role in the server-side session — this is what
    // requireAdmin() checks, so it can't be spoofed from the browser.
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'user' => $user
    ]);
}

function handleSignup($db, $data) {
    $name = trim($data['name'] ?? '');
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    // Self-signup can only ever create student accounts. Admin accounts are
    // pre-seeded only (see initDatabase) — this can't be overridden by
    // whatever the client sends, even a hand-crafted request.
    $role = 'student';

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
    $stmt->execute([
        ':n' => $name,
        ':u' => $username,
        ':p' => $password,
        ':r' => $role
    ]);
    $userId = $db->lastInsertId();

    if ($role === 'student') {
        $stmt = $db->prepare("INSERT OR IGNORE INTO user_progress (user_id, powerups_json, badges_json, subject_stats_json) VALUES (:uid, :pu, :bdg, :subj)");
        $stmt->execute([
            ':uid' => $userId,
            ':pu' => json_encode(['p50' => 3, 'skip' => 2, 'hint' => 2, 'sec' => 1]),
            ':bdg' => json_encode([]),
            ':subj' => json_encode(new stdClass())
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully! You can now log in.',
        'user_id' => $userId
    ]);
}

function handleGetQuestions($db, $params) {
    $grade = $params['grade'] ?? 'all';
    $category = $params['category'] ?? 'all';
    $difficulty = $params['difficulty'] ?? 'all';
    $type = $params['type'] ?? 'all';

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
        if (!is_array($choices)) {
            $choices = $row['choices'] ? [$row['choices']] : [];
        }
        $qType = $row['type'] ?? 'multiple_choice';
        if (!$qType) $qType = 'multiple_choice';

        $questions[] = [
            'id' => (int)$row['id'],
            'grade' => $row['grade'],
            'category' => $row['category'],
            'difficulty' => $row['difficulty'],
            'type' => $qType,
            'question' => $row['question'],
            'choices' => $choices,
            'correct' => (int)$row['correct'],
            'correct_text' => $row['correct_text'] ?? '',
            'explanation' => $row['explanation'] ?? ''
        ];
    }

    echo json_encode(['success' => true, 'questions' => $questions]);
}

function handleAddQuestion($db, $data) {
    $grade = trim($data['grade'] ?? '11');
    $category = trim($data['category'] ?? 'Science');
    $difficulty = trim($data['difficulty'] ?? 'Easy');
    $type = trim($data['type'] ?? 'multiple_choice');
    $question = trim($data['question'] ?? '');
    $choices = $data['choices'] ?? [];
    $correct = (int)($data['correct'] ?? 0);
    $correctText = trim($data['correct_text'] ?? '');
    $explanation = trim($data['explanation'] ?? '');

    if (!$question) {
        echo json_encode(['success' => false, 'error' => 'Please provide question text.']);
        return;
    }

    if ($type === 'multiple_choice') {
        if (count($choices) < 4) {
            echo json_encode(['success' => false, 'error' => 'Multiple choice questions require all 4 options (A, B, C, D).']);
            return;
        }
    } elseif ($type === 'true_false') {
        $choices = ['True', 'False'];
    } elseif ($type === 'identification') {
        $choices = [];
        if (!$correctText) {
            echo json_encode(['success' => false, 'error' => 'Please provide the correct answer word/phrase for identification questions.']);
            return;
        }
    }

    $stmt = $db->prepare("INSERT INTO questions (grade, category, difficulty, type, question, choices, correct, correct_text, explanation) VALUES (:g, :c, :d, :t, :q, :ch, :cor, :ctx, :exp)");
    $stmt->execute([
        ':g' => $grade,
        ':c' => $category,
        ':d' => $difficulty,
        ':t' => $type,
        ':q' => $question,
        ':ch' => json_encode($choices),
        ':cor' => $correct,
        ':ctx' => $correctText,
        ':exp' => $explanation ?: 'The correct answer is shown above.'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Question added successfully!',
        'id' => $db->lastInsertId()
    ]);
}

function handleEditQuestion($db, $data) {
    $id = (int)($data['id'] ?? 0);
    $grade = trim($data['grade'] ?? '11');
    $category = trim($data['category'] ?? 'Science');
    $difficulty = trim($data['difficulty'] ?? 'Easy');
    $type = trim($data['type'] ?? 'multiple_choice');
    $question = trim($data['question'] ?? '');
    $choices = $data['choices'] ?? [];
    $correct = (int)($data['correct'] ?? 0);
    $correctText = trim($data['correct_text'] ?? '');
    $explanation = trim($data['explanation'] ?? '');

    if (!$id || !$question) {
        echo json_encode(['success' => false, 'error' => 'Invalid question data or ID.']);
        return;
    }

    if ($type === 'true_false') {
        $choices = ['True', 'False'];
    } elseif ($type === 'identification') {
        $choices = [];
    }

    $stmt = $db->prepare("UPDATE questions SET grade = :g, category = :c, difficulty = :d, type = :t, question = :q, choices = :ch, correct = :cor, correct_text = :ctx, explanation = :exp WHERE id = :id");
    $stmt->execute([
        ':g' => $grade,
        ':c' => $category,
        ':d' => $difficulty,
        ':t' => $type,
        ':q' => $question,
        ':ch' => json_encode($choices),
        ':cor' => $correct,
        ':ctx' => $correctText,
        ':exp' => $explanation,
        ':id' => $id
    ]);

    echo json_encode(['success' => true, 'message' => 'Question updated successfully!']);
}

function handleDeleteQuestion($db, $data) {
    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid question ID.']);
        return;
    }

    $stmt = $db->prepare("DELETE FROM questions WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Question deleted.']);
}

function handleSaveScore($db, $data) {
    $userId = (int)($data['user_id'] ?? 0);
    $studentName = trim($data['student_name'] ?? 'Student');
    $grade = trim($data['grade'] ?? '11');
    $subject = trim($data['subject'] ?? 'General');
    $difficulty = trim($data['difficulty'] ?? 'All');
    $score = (int)($data['score'] ?? 0);
    $total = (int)($data['total_questions'] ?? 1);
    $percentage = round(($score / max(1, $total)) * 100);
    $points = (int)($data['points_earned'] ?? ($score * 10));

    $stmt = $db->prepare("INSERT INTO scores (user_id, student_name, grade, subject, difficulty, score, total_questions, percentage, points_earned) VALUES (:uid, :n, :g, :s, :d, :sc, :tot, :pct, :pts)");
    $stmt->execute([
        ':uid' => $userId ?: null,
        ':n' => $studentName,
        ':g' => $grade,
        ':s' => $subject,
        ':d' => $difficulty,
        ':sc' => $score,
        ':tot' => $total,
        ':pct' => $percentage,
        ':pts' => $points
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Score recorded!',
        'id' => $db->lastInsertId()
    ]);
}

function handleGetLeaderboard($db) {
    // Show ALL registered students with their cumulative total points, level, quizzes taken, and best score
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
        $accuracy = ($r['total_qs'] > 0) ? round(($r['correct_qs'] / $r['total_qs']) * 100) : 0;
        $badgeCount = count(json_decode($r['badges_json'] ?? '[]', true) ?: []);
        $results[] = [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'username'    => $r['username'],
            'total_points'=> (int)$r['total_points'],
            'level'       => (int)$r['level'],
            'quizzes'     => (int)$r['quizzes_taken'],
            'accuracy'    => $accuracy,
            'best_pct'    => (int)($r['best_pct'] ?? 0),
            'best_streak' => (int)$r['best_streak'],
            'badges'      => $badgeCount,
            'joined'      => date('M d, Y', strtotime($r['created_at']))
        ];
    }

    echo json_encode(['success' => true, 'leaderboard' => $results]);
}

function handleGetUserProgress($db, $params) {
    $userId = (int)($params['user_id'] ?? 0);
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
            'user_id' => $userId,
            'points' => 0,
            'level' => 1,
            'lives' => 5,
            'max_lives' => 5,
            'streak' => 0,
            'best_streak' => 0,
            'total_qs' => 0,
            'correct_qs' => 0,
            'powerups_json' => json_encode(['p50' => 3, 'skip' => 2, 'hint' => 2, 'sec' => 1]),
            'badges_json' => json_encode([]),
            'subject_stats_json' => json_encode(new stdClass()),
            'daily_done_date' => null
        ];
    }

    $pu = json_decode($progress['powerups_json'] ?? '', true) ?: ['p50' => 3, 'skip' => 2, 'hint' => 2, 'sec' => 1];
    $badges = json_decode($progress['badges_json'] ?? '', true) ?: [];
    $subj = json_decode($progress['subject_stats_json'] ?? '', true) ?: [];

    echo json_encode([
        'success' => true,
        'progress' => [
            'points' => (int)$progress['points'],
            'level' => (int)$progress['level'],
            'lives' => (int)$progress['lives'],
            'maxLives' => (int)$progress['max_lives'],
            'streak' => (int)$progress['streak'],
            'bestStreak' => (int)$progress['best_streak'],
            'totalQs' => (int)$progress['total_qs'],
            'correctQs' => (int)$progress['correct_qs'],
            'pu' => $pu,
            'badges' => $badges,
            'subj' => $subj,
            'dailyDone' => $progress['daily_done_date']
        ]
    ]);
}

function handleSaveUserProgress($db, $data) {
    $userId = (int)($data['user_id'] ?? 0);
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

    $p = $data['progress'] ?? $data;
    $points = (int)($p['points'] ?? 0);
    $level = (int)($p['level'] ?? (floor($points / 100) + 1));
    $lives = (int)($p['lives'] ?? 5);
    $maxLives = (int)($p['maxLives'] ?? 5);
    $streak = (int)($p['streak'] ?? 0);
    $bestStreak = (int)($p['bestStreak'] ?? 0);
    $totalQs = (int)($p['totalQs'] ?? 0);
    $correctQs = (int)($p['correctQs'] ?? 0);
    $pu = json_encode($p['pu'] ?? ['p50' => 3, 'skip' => 2, 'hint' => 2, 'sec' => 1]);
    $badges = json_encode($p['badges'] ?? []);
    $subj = json_encode($p['subj'] ?? new stdClass());
    $dailyDone = $p['dailyDone'] ?? null;

    $stmt = $db->prepare("INSERT INTO user_progress (user_id, points, level, lives, max_lives, streak, best_streak, total_qs, correct_qs, powerups_json, badges_json, subject_stats_json, daily_done_date, updated_at)
        VALUES (:uid, :pts, :lvl, :liv, :mliv, :str, :bstr, :tq, :cq, :pu, :bdg, :subj, :dd, CURRENT_TIMESTAMP)
        ON CONFLICT(user_id) DO UPDATE SET
            points = :pts, level = :lvl, lives = :liv, max_lives = :mliv, streak = :str, best_streak = :bstr, total_qs = :tq, correct_qs = :cq, powerups_json = :pu, badges_json = :bdg, subject_stats_json = :subj, daily_done_date = :dd, updated_at = CURRENT_TIMESTAMP");

    $stmt->execute([
        ':uid' => $userId,
        ':pts' => $points,
        ':lvl' => $level,
        ':liv' => $lives,
        ':mliv' => $maxLives,
        ':str' => $streak,
        ':bstr' => $bestStreak,
        ':tq' => $totalQs,
        ':cq' => $correctQs,
        ':pu' => $pu,
        ':bdg' => $badges,
        ':subj' => $subj,
        ':dd' => $dailyDone
    ]);

    echo json_encode(['success' => true, 'message' => 'Progress saved.']);
}

function handleGetAdminStats($db) {
    $totalStudents = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $totalQuestions = (int)$db->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    $totalQuizzes = (int)$db->query("SELECT COUNT(*) FROM scores")->fetchColumn();
    $avgAccuracy = (float)$db->query("SELECT COALESCE(AVG(percentage), 0) FROM scores")->fetchColumn();

    $stmt = $db->query("SELECT category, COUNT(*) as cnt FROM questions GROUP BY category");
    $subjCounts = $stmt->fetchAll();

    $stmt = $db->query("SELECT grade, COUNT(*) as cnt FROM questions GROUP BY grade");
    $gradeCounts = $stmt->fetchAll();

    $stmt = $db->query("SELECT type, COUNT(*) as cnt FROM questions GROUP BY type");
    $typeCounts = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'totalStudents' => $totalStudents,
            'totalQuestions' => $totalQuestions,
            'totalQuizzes' => $totalQuizzes,
            'avgAccuracy' => round($avgAccuracy, 1),
            'subjCounts' => $subjCounts,
            'gradeCounts' => $gradeCounts,
            'typeCounts' => $typeCounts
        ]
    ]);
}

function handleGetStudentRecords($db) {
    $stmt = $db->query("SELECT u.id, u.name, u.username, u.created_at,
            COALESCE(p.points, 0) as points,
            COALESCE(p.level, 1) as level,
            COALESCE(p.total_qs, 0) as total_qs,
            COALESCE(p.correct_qs, 0) as correct_qs,
            (SELECT COUNT(*) FROM scores s WHERE s.user_id = u.id) as quizzes_taken,
            (SELECT MAX(percentage) FROM scores s WHERE s.user_id = u.id) as best_pct
        FROM users u
        LEFT JOIN user_progress p ON u.id = p.user_id
        WHERE u.role = 'student'
        ORDER BY points DESC, u.id DESC");
    $students = $stmt->fetchAll();

    echo json_encode(['success' => true, 'students' => $students]);
}

function handleGetQuizHistory($db) {
    $stmt = $db->query("SELECT id, user_id, student_name, grade, subject, difficulty, score, total_questions, percentage, points_earned, created_at FROM scores ORDER BY id DESC LIMIT 100");
    $history = $stmt->fetchAll();

    echo json_encode(['success' => true, 'history' => $history]);
}

function handleDeleteScore($db, $data) {
    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid score ID.']);
        return;
    }
    $stmt = $db->prepare("DELETE FROM scores WHERE id = :id");
    $stmt->execute([':id' => $id]);
    echo json_encode(['success' => true, 'message' => 'Score record deleted.']);
}

// ======================== SEED COMPREHENSIVE QUESTIONS (75 ITEMS) ======================== //

function seedQuestions($db) {
    $seed = [
        // ==================== SCIENCE ==================== //
        // Multiple Choice
        [
            'grade' => '11', 'category' => 'Science', 'difficulty' => 'Easy', 'type' => 'multiple_choice',
            'question' => 'What is the primary chemical formula for Water?',
            'choices' => ['H2O', 'CO2', 'O2', 'NaCl'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'Water consists of 2 Hydrogen atoms bonded to 1 Oxygen atom (H2O).'
        ],
        [
            'grade' => '11', 'category' => 'Science', 'difficulty' => 'Average', 'type' => 'multiple_choice',
            'question' => 'Which cell organelle is known as the "powerhouse of the cell"?',
            'choices' => ['Ribosome', 'Mitochondria', 'Endoplasmic Reticulum', 'Golgi Apparatus'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Mitochondria produce ATP through cellular respiration, supplying cellular energy.'
        ],
        [
            'grade' => '11', 'category' => 'Science', 'difficulty' => 'Difficult', 'type' => 'multiple_choice',
            'question' => 'Which type of rock is formed from the cooling and solidification of molten rock (magma or lava)?',
            'choices' => ['Sedimentary', 'Metamorphic', 'Igneous', 'Limestone'], 'correct' => 2, 'correct_text' => '',
            'explanation' => 'Igneous rocks (like basalt and granite) crystallize directly from molten magma or lava.'
        ],
        [
            'grade' => '11', 'category' => 'Science', 'difficulty' => 'Mastery', 'type' => 'multiple_choice',
            'question' => 'In plate tectonics, what landform is typically formed when two continental plates collide?',
            'choices' => ['Mid-ocean ridge', 'Mountain range', 'Deep-sea trench', 'Rift valley'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Continental-continental convergence causes crustal crumpling that forms high mountain ranges like the Himalayas.'
        ],
        [
            'grade' => '12', 'category' => 'Science', 'difficulty' => 'Easy', 'type' => 'multiple_choice',
            'question' => 'Which planet in our solar system is known as the Red Planet?',
            'choices' => ['Venus', 'Mars', 'Jupiter', 'Mercury'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Mars appears red due to iron oxide (rust) on its surface.'
        ],
        [
            'grade' => '12', 'category' => 'Science', 'difficulty' => 'Average', 'type' => 'multiple_choice',
            'question' => 'What type of chemical bond involves the transfer of electrons from a metal to a non-metal?',
            'choices' => ['Covalent bond', 'Ionic bond', 'Metallic bond', 'Hydrogen bond'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'An ionic bond is formed through electrostatic attraction after the complete transfer of valence electrons.'
        ],
        [
            'grade' => '12', 'category' => 'Science', 'difficulty' => 'Difficult', 'type' => 'multiple_choice',
            'question' => 'Which law states that energy cannot be created or destroyed, only transformed?',
            'choices' => ['First Law of Thermodynamics', 'Second Law of Thermodynamics', 'Law of Conservation of Momentum', 'Newton\'s Third Law'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'The First Law of Thermodynamics is the Law of Conservation of Energy.'
        ],
        // True or False
        [
            'grade' => '11', 'category' => 'Science', 'difficulty' => 'Easy', 'type' => 'true_false',
            'question' => 'Photosynthesis takes place primarily in the chloroplasts of plant cells.',
            'choices' => ['True', 'False'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'Chloroplasts contain chlorophyll which absorbs sunlight for photosynthesis.'
        ],
        [
            'grade' => '11', 'category' => 'Science', 'difficulty' => 'Average', 'type' => 'true_false',
            'question' => 'Sound travels faster in a vacuum than through air.',
            'choices' => ['True', 'False'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Sound is a mechanical wave and requires a physical medium to travel; it cannot travel in a vacuum.'
        ],
        [
            'grade' => '12', 'category' => 'Science', 'difficulty' => 'Difficult', 'type' => 'true_false',
            'question' => 'DNA contains the nitrogenous base Uracil instead of Thymine.',
            'choices' => ['True', 'False'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'RNA contains Uracil; DNA contains Thymine.'
        ],
        [
            'grade' => '12', 'category' => 'Science', 'difficulty' => 'Average', 'type' => 'true_false',
            'question' => 'The ozone layer is primarily situated within the Stratosphere of Earth\'s atmosphere.',
            'choices' => ['True', 'False'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'The ozone layer resides in the stratosphere between 15 and 35 km above Earth.'
        ],
        // Identification
        [
            'grade' => '11', 'category' => 'Science', 'difficulty' => 'Easy', 'type' => 'identification',
            'question' => 'What is the chemical symbol for Gold in the periodic table?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Au',
            'explanation' => 'Au comes from the Latin word "Aurum".'
        ],
        [
            'grade' => '11', 'category' => 'Science', 'difficulty' => 'Average', 'type' => 'identification',
            'question' => 'What organelle is known as the powerhouse of the cell?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Mitochondria',
            'explanation' => 'Mitochondria produce cellular ATP energy.'
        ],
        [
            'grade' => '12', 'category' => 'Science', 'difficulty' => 'Difficult', 'type' => 'identification',
            'question' => 'What is the closest star to the Earth?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Sun',
            'explanation' => 'The Sun is the star at the center of the Solar System.'
        ],
        [
            'grade' => '12', 'category' => 'Science', 'difficulty' => 'Mastery', 'type' => 'identification',
            'question' => 'What biological macromolecule carries the genetic instructions for all living organisms? (Acronym)',
            'choices' => [], 'correct' => 0, 'correct_text' => 'DNA',
            'explanation' => 'DNA stands for Deoxyribonucleic Acid.'
        ],

        // ==================== MATHEMATICS ==================== //
        // Multiple Choice
        [
            'grade' => '11', 'category' => 'Mathematics', 'difficulty' => 'Easy', 'type' => 'multiple_choice',
            'question' => 'What is the product of 7 multiplied by 8?',
            'choices' => ['54', '56', '49', '63'], 'correct' => 1, 'correct_text' => '',
            'explanation' => '7 × 8 = 56.'
        ],
        [
            'grade' => '11', 'category' => 'Mathematics', 'difficulty' => 'Average', 'type' => 'multiple_choice',
            'question' => 'If f(x) = 3x + 5, what is the value of f(4)?',
            'choices' => ['12', '15', '17', '21'], 'correct' => 2, 'correct_text' => '',
            'explanation' => 'f(4) = 3(4) + 5 = 12 + 5 = 17.'
        ],
        [
            'grade' => '11', 'category' => 'Mathematics', 'difficulty' => 'Difficult', 'type' => 'multiple_choice',
            'question' => 'What is the simple interest on Php 10,000 invested at 5% annual rate for 3 years? (Formula: I = Prt)',
            'choices' => ['Php 1,000', 'Php 1,500', 'Php 2,000', 'Php 500'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'I = 10,000 × 0.05 × 3 = Php 1,500.'
        ],
        [
            'grade' => '11', 'category' => 'Mathematics', 'difficulty' => 'Mastery', 'type' => 'multiple_choice',
            'question' => 'Which of the following represents a rational inequality?',
            'choices' => ['(2x - 1) / (x + 3) > 0', 'y = 2x^2 + 5x', '3x + 4 = 19', 'f(x) = sqrt(x - 2)'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'A rational inequality involves rational expressions with inequality symbols (>, <, >=, <=).'
        ],
        [
            'grade' => '12', 'category' => 'Mathematics', 'difficulty' => 'Easy', 'type' => 'multiple_choice',
            'question' => 'In statistics, what measure of central tendency represents the most frequently occurring value?',
            'choices' => ['Mean', 'Median', 'Mode', 'Variance'], 'correct' => 2, 'correct_text' => '',
            'explanation' => 'The mode is the value with highest frequency.'
        ],
        [
            'grade' => '12', 'category' => 'Mathematics', 'difficulty' => 'Average', 'type' => 'multiple_choice',
            'question' => 'In a standard normal distribution (Z-distribution), what are the mean and standard deviation?',
            'choices' => ['Mean = 1, SD = 0', 'Mean = 0, SD = 1', 'Mean = 50, SD = 10', 'Mean = 100, SD = 15'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'A standard normal curve has a mean (μ) of 0 and a standard deviation (σ) of 1.'
        ],
        [
            'grade' => '12', 'category' => 'Mathematics', 'difficulty' => 'Difficult', 'type' => 'multiple_choice',
            'question' => 'How many unique 4-digit PIN codes can be formed from digits 0 to 9 if repetition is NOT allowed? (Formula: P(10,4))',
            'choices' => ['5,040', '10,000', '2,520', '720'], 'correct' => 0, 'correct_text' => '',
            'explanation' => '10 × 9 × 8 × 7 = 5,040 permutations.'
        ],
        // True or False
        [
            'grade' => '11', 'category' => 'Mathematics', 'difficulty' => 'Easy', 'type' => 'true_false',
            'question' => 'The sum of all interior angles in any Euclidean triangle is always 180 degrees.',
            'choices' => ['True', 'False'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'The triangle angle sum theorem dictates that the 3 interior angles sum to 180°.'
        ],
        [
            'grade' => '11', 'category' => 'Mathematics', 'difficulty' => 'Average', 'type' => 'true_false',
            'question' => 'Zero is categorized as a positive integer.',
            'choices' => ['True', 'False'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Zero is neutral: it is neither positive nor negative.'
        ],
        [
            'grade' => '12', 'category' => 'Mathematics', 'difficulty' => 'Easy', 'type' => 'true_false',
            'question' => 'The square root of 64 is equal to 8.',
            'choices' => ['True', 'False'], 'correct' => 0, 'correct_text' => '',
            'explanation' => '8 × 8 = 64.'
        ],
        [
            'grade' => '12', 'category' => 'Mathematics', 'difficulty' => 'Difficult', 'type' => 'true_false',
            'question' => 'A rational number cannot be represented as a ratio of two integers.',
            'choices' => ['True', 'False'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'By definition, rational numbers can be expressed as a fraction a/b where b is non-zero.'
        ],
        // Identification
        [
            'grade' => '11', 'category' => 'Mathematics', 'difficulty' => 'Easy', 'type' => 'identification',
            'question' => 'What is the value of Pi rounded to two decimal places?',
            'choices' => [], 'correct' => 0, 'correct_text' => '3.14',
            'explanation' => 'Pi (π) is approximately 3.14159...'
        ],
        [
            'grade' => '11', 'category' => 'Mathematics', 'difficulty' => 'Average', 'type' => 'identification',
            'question' => 'In a right triangle, what famous theorem states that a^2 + b^2 = c^2?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Pythagorean Theorem',
            'explanation' => 'Named after ancient Greek mathematician Pythagoras.'
        ],
        [
            'grade' => '12', 'category' => 'Mathematics', 'difficulty' => 'Average', 'type' => 'identification',
            'question' => 'What is the perimeter of a square with a side length of 5 cm?',
            'choices' => [], 'correct' => 0, 'correct_text' => '20',
            'explanation' => 'Perimeter = 4 × side = 4 × 5 = 20 cm.'
        ],
        [
            'grade' => '12', 'category' => 'Mathematics', 'difficulty' => 'Difficult', 'type' => 'identification',
            'question' => 'What mathematical term describes a polynomial consisting of exactly two terms?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Binomial',
            'explanation' => 'Bi- means two, as in binomial.'
        ],

        // ==================== ENGLISH ==================== //
        // Multiple Choice
        [
            'grade' => '11', 'category' => 'English', 'difficulty' => 'Easy', 'type' => 'multiple_choice',
            'question' => 'Which part of speech names a person, place, thing, or idea?',
            'choices' => ['Verb', 'Adjective', 'Noun', 'Adverb'], 'correct' => 2, 'correct_text' => '',
            'explanation' => 'A noun is a naming word for people, places, things, or concepts.'
        ],
        [
            'grade' => '11', 'category' => 'English', 'difficulty' => 'Average', 'type' => 'multiple_choice',
            'question' => 'Which figure of speech directly compares two unlike things using "like" or "as"?',
            'choices' => ['Metaphor', 'Simile', 'Personification', 'Hyperbole'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Similes explicitly compare using words such as "like" or "as".'
        ],
        [
            'grade' => '11', 'category' => 'English', 'difficulty' => 'Difficult', 'type' => 'multiple_choice',
            'question' => 'Identify the sentence with correct subject-verb agreement:',
            'choices' => [
                'Each of the students have submitted their project.',
                'The bouquet of red roses smell wonderful.',
                'Neither the teacher nor the students were absent yesterday.',
                'A number of books was donated to the library.'
            ], 'correct' => 2, 'correct_text' => '',
            'explanation' => 'In "neither... nor", the verb agrees with the closer subject ("students were").'
        ],
        [
            'grade' => '11', 'category' => 'English', 'difficulty' => 'Mastery', 'type' => 'multiple_choice',
            'question' => 'In academic writing, what is the central argument or claim of an essay called?',
            'choices' => ['Topic sentence', 'Thesis statement', 'Bibliography', 'Hook'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'A thesis statement encapsulates the main claim and scope of an academic paper.'
        ],
        [
            'grade' => '12', 'category' => 'English', 'difficulty' => 'Easy', 'type' => 'multiple_choice',
            'question' => 'Which punctuation mark is used to indicate a direct quotation or title of a short work?',
            'choices' => ['Parentheses', 'Quotation marks', 'Apostrophe', 'Hyphen'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Quotation marks (" ") enclose direct speech or citations.'
        ],
        [
            'grade' => '12', 'category' => 'English', 'difficulty' => 'Average', 'type' => 'multiple_choice',
            'question' => 'What reading strategy involves quickly scanning a text to locate specific information?',
            'choices' => ['Skimming', 'Scanning', 'Extensive reading', 'In-depth analysis'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Scanning is looking for specific keywords or facts without reading every word.'
        ],
        [
            'grade' => '12', 'category' => 'English', 'difficulty' => 'Difficult', 'type' => 'multiple_choice',
            'question' => 'What is the fallacious argument where one attacks the person\'s character rather than their argument?',
            'choices' => ['Straw Man', 'Ad Hominem', 'Bandwagon', 'False Dilemma'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Ad hominem (Latin: "to the person") attacks character instead of the issue.'
        ],
        // True or False
        [
            'grade' => '11', 'category' => 'English', 'difficulty' => 'Easy', 'type' => 'true_false',
            'question' => 'The word "quickly" functions as an adverb in modern English grammar.',
            'choices' => ['True', 'False'], 'correct' => 0, 'correct_text' => '',
            'explanation' => '"Quickly" describes how an action is performed, modifying verbs.'
        ],
        [
            'grade' => '11', 'category' => 'English', 'difficulty' => 'Average', 'type' => 'true_false',
            'question' => 'An antonym is a word that has the identical meaning as another word.',
            'choices' => ['True', 'False'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'An antonym has opposite meaning; synonyms have similar meanings.'
        ],
        [
            'grade' => '12', 'category' => 'English', 'difficulty' => 'Average', 'type' => 'true_false',
            'question' => 'A thesis statement is usually positioned near the end of the introductory paragraph.',
            'choices' => ['True', 'False'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'Placing the thesis at the end of the introduction transitions effectively into body paragraphs.'
        ],
        [
            'grade' => '12', 'category' => 'English', 'difficulty' => 'Difficult', 'type' => 'true_false',
            'question' => 'Plagiarism is acceptable as long as you rewrite the passage in your own words without citation.',
            'choices' => ['True', 'False'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Paraphrasing without attributing the original author is still plagiarism.'
        ],
        // Identification
        [
            'grade' => '11', 'category' => 'English', 'difficulty' => 'Average', 'type' => 'identification',
            'question' => 'What figure of speech gives human attributes or feelings to non-human objects?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Personification',
            'explanation' => 'Example: "The wind whispered through the trees."'
        ],
        [
            'grade' => '11', 'category' => 'English', 'difficulty' => 'Easy', 'type' => 'identification',
            'question' => 'What punctuation mark is used at the end of an asking or interrogative sentence?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Question mark',
            'explanation' => 'The question mark (?) indicates an inquiry.'
        ],
        [
            'grade' => '12', 'category' => 'English', 'difficulty' => 'Average', 'type' => 'identification',
            'question' => 'What literary term refers to the main character or hero in a narrative story?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Protagonist',
            'explanation' => 'The protagonist drives the main storyline.'
        ],
        [
            'grade' => '12', 'category' => 'English', 'difficulty' => 'Easy', 'type' => 'identification',
            'question' => 'What is the comparative degree of the adjective "good"?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Better',
            'explanation' => 'Good, Better, Best.'
        ],

        // ==================== FILIPINO ==================== //
        // Multiple Choice
        [
            'grade' => '11', 'category' => 'Filipino', 'difficulty' => 'Easy', 'type' => 'multiple_choice',
            'question' => 'Ano ang pambansang wika ng Pilipinas ayon sa Saligang Batas ng 1987?',
            'choices' => ['Tagalog', 'Filipino', 'Pilipino', 'Ingles'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Ayon sa Saligang Batas ng 1987, Artikulo XIV, Seksyon 6: Ang wikang pambansa ay Filipino.'
        ],
        [
            'grade' => '11', 'category' => 'Filipino', 'difficulty' => 'Average', 'type' => 'multiple_choice',
            'question' => 'Anong barayti ng wika ang tumutukoy sa pansariling paraan ng pagsasalita ng isang indibidwal?',
            'choices' => ['Sosyolek', 'Diyalekto', 'Idyolek', 'Etnolek'], 'correct' => 2, 'correct_text' => '',
            'explanation' => 'Ang idyolek ay ang indibidwal at natatanging estilo o tatak sa pagsasalita.'
        ],
        [
            'grade' => '11', 'category' => 'Filipino', 'difficulty' => 'Difficult', 'type' => 'multiple_choice',
            'question' => 'Anong gamit ng wika ayon kay M.A.K. Halliday ang ginagamit sa pagpapahayag ng sariling damdamin at opinyon?',
            'choices' => ['Instrumental', 'Personal', 'Interaksiyonal', 'Heuristiko'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Ang personal na tungkulin ng wika ay nagpapahayag ng sariling pananaw at emosyon.'
        ],
        [
            'grade' => '11', 'category' => 'Filipino', 'difficulty' => 'Mastery', 'type' => 'multiple_choice',
            'question' => 'Ano ang tawag sa sinaunang sistema ng pagsulat ng mga katutubong Pilipino bago dumating ang mga Espanyol?',
            'choices' => ['Abakada', 'Baybayin', 'Alibata', 'Alpabetong Romano'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Baybayin ang tamang tawag sa sinaunang katutubong paraan ng pagsulat ng mga Pilipino.'
        ],
        [
            'grade' => '12', 'category' => 'Filipino', 'difficulty' => 'Easy', 'type' => 'multiple_choice',
            'question' => 'Ano ang kahulugan ng salitang "saliksik" o "pananaliksik"?',
            'choices' => ['Paghula', 'Makaagham at sistematikong pagsusuri', 'Kuwentong bayan', 'Pangongopya'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Ang pananaliksik ay isang sistematiko at makaagham na proseso ng pagtuklas ng impormasyon.'
        ],
        [
            'grade' => '12', 'category' => 'Filipino', 'difficulty' => 'Average', 'type' => 'multiple_choice',
            'question' => 'Aling bahagi ng pananaliksik ang naglalaman ng maikling buod ng buong pag-aaral?',
            'choices' => ['Metodolohiya', 'Abstrak', 'Konklusyon', 'Talasanggunian'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Ang abstrak ay maikling buod ng pananaliksik.'
        ],
        [
            'grade' => '12', 'category' => 'Filipino', 'difficulty' => 'Difficult', 'type' => 'multiple_choice',
            'question' => 'Ano ang tawag sa ilegal na pagkopya o pag-angkin ng gawa, ideya, o salita ng iba nang walang wastong pagkilala?',
            'choices' => ['Paraphrasing', 'Plagiarism', 'Sintesis', 'Bibliyograpiya'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Ang plagiarism ay paglabag sa karapatang-ari sa pamamagitan ng pag-angkin ng gawa ng iba nang walang credit.'
        ],
        // True or False
        [
            'grade' => '11', 'category' => 'Filipino', 'difficulty' => 'Easy', 'type' => 'true_false',
            'question' => 'Ang Tagalog, Pilipino, at Filipino ay magkakatulad at walang pagkakaiba sa kasaysayan.',
            'choices' => ['True', 'False'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'May pagkakaiba: ang Tagalog ay katutubong wika, Pilipino ang pambansang wika noong 1959, at Filipino noong 1987.'
        ],
        [
            'grade' => '11', 'category' => 'Filipino', 'difficulty' => 'Average', 'type' => 'true_false',
            'question' => 'Ang idyolek ay ang personal o natatanging estilo ng pagsasalita ng isang partikular na tao.',
            'choices' => ['True', 'False'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'Tama. Halimbawa ang kilalang estilo ni Mike Enriquez o Noli de Castro.'
        ],
        [
            'grade' => '12', 'category' => 'Filipino', 'difficulty' => 'Easy', 'type' => 'true_false',
            'question' => 'Ayon sa batas, ang pambansang wika ng Pilipinas ay Filipino.',
            'choices' => ['True', 'False'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'Tama, alinsunod sa 1987 Philippine Constitution Art. XIV Sec. 6.'
        ],
        [
            'grade' => '12', 'category' => 'Filipino', 'difficulty' => 'Difficult', 'type' => 'true_false',
            'question' => 'Ang talasanggunian o bibliyograpiya ay inilalagay sa pinakaunang pahina ng isang pamanahong papel.',
            'choices' => ['True', 'False'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Mali. Ang talasanggunian ay inilalagay sa dulo ng pananaliksik.'
        ],
        // Identification
        [
            'grade' => '11', 'category' => 'Filipino', 'difficulty' => 'Average', 'type' => 'identification',
            'question' => 'Sino ang kinikilalang "Ama ng Wikang Pambansa" ng Pilipinas?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Manuel L. Quezon',
            'explanation' => 'Si Pangulong Manuel L. Quezon ang nagsulong ng pagkakaroon ng pambansang wika.'
        ],
        [
            'grade' => '11', 'category' => 'Filipino', 'difficulty' => 'Mastery', 'type' => 'identification',
            'question' => 'Ano ang katutubong sistema ng pagsulat ng mga Pilipino bago dumating ang mga Espanyol?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Baybayin',
            'explanation' => 'Baybayin ang sinaunang katutubong pagsulat.'
        ],
        [
            'grade' => '12', 'category' => 'Filipino', 'difficulty' => 'Average', 'type' => 'identification',
            'question' => 'Anong barayti ng wika ang nalilikha dahil sa heograpikal na dimensiyon o lokasyon?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Diyalekto',
            'explanation' => 'Halimbawa: Tagalog-Batangas, Tagalog-Bulacan.'
        ],
        [
            'grade' => '12', 'category' => 'Filipino', 'difficulty' => 'Easy', 'type' => 'identification',
            'question' => 'Ano ang tawag sa maikling buod ng isang akademikong sulatin o pananaliksik?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Abstrak',
            'explanation' => 'Ang abstrak ay maikling buod sa unahan ng pananaliksik.'
        ],

        // ==================== SOCIAL SCIENCE ==================== //
        // Multiple Choice
        [
            'grade' => '11', 'category' => 'Social Science', 'difficulty' => 'Easy', 'type' => 'multiple_choice',
            'question' => 'Sino ang pambansang bayani ng Pilipinas na sumulat ng "Noli Me Tangere" at "El Filibusterismo"?',
            'choices' => ['Andres Bonifacio', 'Dr. Jose Rizal', 'Emilio Aguinaldo', 'Apolinario Mabini'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Isinulat ni Dr. Jose Rizal ang Noli Me Tangere at El Filibusterismo.'
        ],
        [
            'grade' => '11', 'category' => 'Social Science', 'difficulty' => 'Average', 'type' => 'multiple_choice',
            'question' => 'What sociological concept refers to the belief that one\'s own culture is superior to all other cultures?',
            'choices' => ['Cultural Relativism', 'Ethnocentrism', 'Xenocentrism', 'Subculture'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Ethnocentrism is evaluating other cultures through standards of one\'s own culture.'
        ],
        [
            'grade' => '11', 'category' => 'Social Science', 'difficulty' => 'Difficult', 'type' => 'multiple_choice',
            'question' => 'Which branch of the Philippine government is constitutionally authorized to interpret and apply laws?',
            'choices' => ['Executive Branch', 'Legislative Branch', 'Judicial Branch', 'Civil Service Commission'], 'correct' => 2, 'correct_text' => '',
            'explanation' => 'The Judicial Branch (headed by the Supreme Court) holds the judicial power.'
        ],
        [
            'grade' => '11', 'category' => 'Social Science', 'difficulty' => 'Mastery', 'type' => 'multiple_choice',
            'question' => 'What is the term for the intangible aspects of culture including values, norms, beliefs, and language?',
            'choices' => ['Material Culture', 'Non-material Culture', 'Archaeology', 'Artifacts'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Non-material culture consists of the ideas, attitudes, and beliefs of a society.'
        ],
        [
            'grade' => '12', 'category' => 'Social Science', 'difficulty' => 'Easy', 'type' => 'multiple_choice',
            'question' => 'How many years is one regular term of the President of the Philippines under the 1987 Constitution?',
            'choices' => ['4 years', '5 years', '6 years', '7 years'], 'correct' => 2, 'correct_text' => '',
            'explanation' => 'Under Article VII of the 1987 Constitution, the President serves a single 6-year term.'
        ],
        [
            'grade' => '12', 'category' => 'Social Science', 'difficulty' => 'Average', 'type' => 'multiple_choice',
            'question' => 'What form of government is officially practiced in the Philippines?',
            'choices' => ['Parliamentary Democracy', 'Unitary Presidential Constitutional Republic', 'Federal Monarchy', 'Socialist State'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'The Philippines is governed as a unitary presidential constitutional republic.'
        ],
        [
            'grade' => '12', 'category' => 'Social Science', 'difficulty' => 'Difficult', 'type' => 'multiple_choice',
            'question' => 'What is the highest law of the land in the Republic of the Philippines?',
            'choices' => ['Civil Code', 'Revised Penal Code', 'The 1987 Philippine Constitution', 'Republic Act 10533'], 'correct' => 2, 'correct_text' => '',
            'explanation' => 'The Constitution is the fundamental and supreme law of the land.'
        ],
        // True or False
        [
            'grade' => '11', 'category' => 'Social Science', 'difficulty' => 'Easy', 'type' => 'true_false',
            'question' => 'The legislative branch of the Philippine government is composed of the Senate and the House of Representatives.',
            'choices' => ['True', 'False'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'The Congress of the Philippines is bicameral.'
        ],
        [
            'grade' => '11', 'category' => 'Social Science', 'difficulty' => 'Average', 'type' => 'true_false',
            'question' => 'Cultural relativism argues that cultures should be judged using the standards of Western societies.',
            'choices' => ['True', 'False'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'Cultural relativism states that a person\'s beliefs and practices should be understood based on that person\'s own culture.'
        ],
        [
            'grade' => '12', 'category' => 'Social Science', 'difficulty' => 'Easy', 'type' => 'true_false',
            'question' => 'Under Philippine law, an accused person is presumed innocent until proven guilty beyond reasonable doubt.',
            'choices' => ['True', 'False'], 'correct' => 0, 'correct_text' => '',
            'explanation' => 'Presumption of innocence is a constitutional right under the Bill of Rights.'
        ],
        [
            'grade' => '12', 'category' => 'Social Science', 'difficulty' => 'Difficult', 'type' => 'true_false',
            'question' => 'The 1987 Philippine Constitution was ratified during the administration of President Ferdinand Marcos Sr.',
            'choices' => ['True', 'False'], 'correct' => 1, 'correct_text' => '',
            'explanation' => 'It was ratified during the administration of President Corazon C. Aquino.'
        ],
        // Identification
        [
            'grade' => '11', 'category' => 'Social Science', 'difficulty' => 'Easy', 'type' => 'identification',
            'question' => 'Who is the national hero of the Philippines who wrote Noli Me Tangere?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Jose Rizal',
            'explanation' => 'Dr. Jose Rizal.'
        ],
        [
            'grade' => '11', 'category' => 'Social Science', 'difficulty' => 'Average', 'type' => 'identification',
            'question' => 'What is the supreme and fundamental law of the land in the Philippines?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Constitution',
            'explanation' => 'The 1987 Philippine Constitution.'
        ],
        [
            'grade' => '12', 'category' => 'Social Science', 'difficulty' => 'Average', 'type' => 'identification',
            'question' => 'What branch of the Philippine government is headed by the President and Vice President?',
            'choices' => [], 'correct' => 0, 'correct_text' => 'Executive',
            'explanation' => 'The Executive branch implements and enforces laws.'
        ],
        [
            'grade' => '12', 'category' => 'Social Science', 'difficulty' => 'Difficult', 'type' => 'identification',
            'question' => 'What constitutional commission in the Philippines is tasked with administering elections? (Acronym)',
            'choices' => [], 'correct' => 0, 'correct_text' => 'COMELEC',
            'explanation' => 'Commission on Elections (COMELEC).'
        ]
    ];

    $stmt = $db->prepare("INSERT INTO questions (grade, category, difficulty, type, question, choices, correct, correct_text, explanation) VALUES (:g, :c, :d, :t, :q, :ch, :cor, :ctx, :exp)");
    foreach ($seed as $q) {
        $stmt->execute([
            ':g' => $q['grade'],
            ':c' => $q['category'],
            ':d' => $q['difficulty'],
            ':t' => $q['type'],
            ':q' => $q['question'],
            ':ch' => json_encode($q['choices']),
            ':cor' => $q['correct'],
            ':ctx' => $q['correct_text'],
            ':exp' => $q['explanation']
        ]);
    }
}
