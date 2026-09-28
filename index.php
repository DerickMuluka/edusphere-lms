<?php
/**
 * EduSphere LMS — Public landing page (v2)
 * Serves marketing content only. Auth forms are NOT linked
 * from this page — visitors enter the portal through a JS
 * overlay that loads portal.php in an iframe.
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(SITE_URL . '/pages/dashboard.php');
}

$__conn = db();

$__previewCourses = $__conn->query("
    SELECT c.id, c.code, c.title, c.description, c.level, c.duration_hours,
           d.code AS dept_code, d.name AS dept_name,
           u.full_name AS instructor_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) AS enrolled
    FROM courses c
    LEFT JOIN departments d ON d.id = c.department_id
    JOIN users u ON u.id = c.instructor_id
    WHERE c.is_published = 1
    ORDER BY (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) DESC, c.code
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$__departments = $__conn->query("
    SELECT d.id, d.code, d.name, d.description, d.head_name,
           (SELECT COUNT(*) FROM courses WHERE department_id = d.id AND is_published = 1) AS course_count
    FROM departments d ORDER BY d.name
")->fetch_all(MYSQLI_ASSOC);

$__stats = $__conn->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE role='student' AND status='active') AS students,
        (SELECT COUNT(*) FROM users WHERE role='instructor' AND status='active') AS instructors,
        (SELECT COUNT(*) FROM courses WHERE is_published=1) AS courses,
        (SELECT COUNT(*) FROM departments) AS departments
")->fetch_assoc();

function __f($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduSphere — Learning that moves you forward</title>
    <meta name="description" content="EduSphere is a modern learning platform for schools, colleges, and training institutes. Courses, assignments, timed exams, past papers, and progress tracking.">
    <meta name="theme-color" content="#0b0a09">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Fraunces:opsz,wght@9..144,500;9..144,700;9..144,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo __f(SITE_URL); ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?php echo __f(SITE_URL); ?>/assets/css/landing.css">
</head>
<body class="landing">

<div class="grain"></div>
<div class="cursor-glow" id="cursorGlow"></div>

<!-- ============ NAVIGATION ============ -->
<header class="nav" id="nav">
    <div class="nav__inner">
        <a class="brand" href="/">
            <span class="brand__mark">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3 L3 8 L12 13 L21 8 Z"/>
                    <path d="M3 13 L12 18 L21 13"/>
                </svg>
            </span>
            <span class="brand__text">Edu<span>Sphere</span></span>
        </a>

        <nav class="nav__links" id="navLinks">
            <a href="#features" data-scroll>Features</a>
            <a href="#departments" data-scroll>Departments</a>
            <a href="#courses" data-scroll>Courses</a>
            <a href="#how" data-scroll>How it works</a>
            <a href="#faq" data-scroll>FAQ</a>
        </nav>

        <div class="nav__cta">
            <button class="btn btn--ghost" type="button" data-portal="login">Sign in</button>
            <button class="btn btn--primary" type="button" data-portal="register">Get started</button>
        </div>

        <button class="nav__burger" id="navBurger" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>
    <div class="nav__progress" id="navProgress"></div>
</header>

<!-- ============ HERO ============ -->
<section class="hero">
    <div class="hero__backdrop">
        <div class="hero__orb hero__orb--1"></div>
        <div class="hero__orb hero__orb--2"></div>
        <div class="hero__orb hero__orb--3"></div>
        <div class="hero__grid"></div>
    </div>

    <div class="hero__inner">
        <div class="hero__copy reveal">
            <span class="pill">
                <span class="pill__dot"></span>
                Now enrolling for the 2026 academic year
            </span>

            <h1 class="hero__title">
                Learn like you mean it.
                <em>Graduate like you did.</em>
            </h1>

            <p class="hero__lead">
                EduSphere is the learning platform for schools, colleges and training
                institutes that refuse to settle for a clunky experience. Courses,
                assignments, timed exams, past papers and progress — in one place,
                beautifully organised.
            </p>

            <div class="hero__actions">
                <button class="btn btn--primary btn--xl" type="button" data-portal="register">
                    Start your application
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                </button>
                <button class="btn btn--glass btn--xl" type="button" data-portal="login">
                    Sign in to portal
                </button>
            </div>

            <div class="hero__trust">
                <div class="trust-avatars">
                    <span class="trust-avatars__a" style="--bg:#b8543c">JW</span>
                    <span class="trust-avatars__a" style="--bg:#6b8a63">KK</span>
                    <span class="trust-avatars__a" style="--bg:#d99b34">MA</span>
                    <span class="trust-avatars__a" style="--bg:#4a4540">DM</span>
                </div>
                <div class="trust-copy">
                    <strong><?php echo (int)$__stats['students']; ?>+ active students</strong>
                    <span>across <?php echo (int)$__stats['departments']; ?> departments</span>
                </div>
            </div>
        </div>

        <div class="hero__visual reveal" data-delay="120">
            <div class="scene">
                <div class="scene__card scene__card--main">
                    <div class="scene__head">
                        <div class="scene__dots"><span></span><span></span><span></span></div>
                        <span class="scene__file">cs201-progress.live</span>
                    </div>
                    <div class="scene__body">
                        <div class="scene__row">
                            <div>
                                <div class="scene__kicker">Currently enrolled</div>
                                <div class="scene__title">CS201 — Data Structures</div>
                            </div>
                            <div class="scene__badge">68%</div>
                        </div>
                        <div class="scene__bar"><div style="width:68%"></div></div>
                        <ul class="scene__list">
                            <li><span class="dot dot--done"></span> Arrays &amp; Lists</li>
                            <li><span class="dot dot--done"></span> Linked Lists</li>
                            <li><span class="dot dot--done"></span> Stacks &amp; Queues</li>
                            <li><span class="dot"></span> Trees &amp; Traversals</li>
                        </ul>
                    </div>
                </div>

                <div class="scene__chip scene__chip--a">
                    <span class="chip-icon chip-icon--warn">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </span>
                    <div>
                        <div class="chip-title">Assignment due</div>
                        <div class="chip-sub">Linked List · in 2 days</div>
                    </div>
                </div>

                <div class="scene__chip scene__chip--b">
                    <span class="chip-icon chip-icon--ok">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <div>
                        <div class="chip-title">Exam graded</div>
                        <div class="chip-sub">CS101 · 18/20</div>
                    </div>
                </div>

                <div class="scene__glow"></div>
            </div>
        </div>
    </div>
</section>

<!-- ============ LOGO STRIP ============ -->
<section class="strip">
    <div class="strip__inner">
        <span class="strip__label">Built for</span>
        <ul class="strip__list">
            <li>Universities</li>
            <li>Colleges</li>
            <li>High Schools</li>
            <li>Training Institutes</li>
            <li>Corporate Learning</li>
        </ul>
    </div>
</section>

<!-- ============ FEATURES ============ -->
<section class="section" id="features">
    <div class="section__inner">
        <header class="section__head reveal">
            <span class="kicker">Everything in one place</span>
            <h2 class="section__title">The platform a real campus deserves</h2>
            <p class="section__lead">
                Every screen in EduSphere was designed around a specific teaching workflow.
                No features bolted on for the sake of a checklist.
            </p>
        </header>

        <div class="bento">
            <article class="bento__cell bento__cell--lg reveal">
                <div class="bento__icon bento__icon--coral">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                </div>
                <h3>Structured, ordered lessons</h3>
                <p>Every course is broken into sequential lessons with video, notes and downloadable attachments. Students always know what comes next — and what they've already mastered.</p>
                <div class="bento__demo">
                    <div class="mini-lesson"><span class="mini-lesson__n">01</span> Environment Setup</div>
                    <div class="mini-lesson is-done"><span class="mini-lesson__n">02</span> Variables &amp; Types</div>
                    <div class="mini-lesson is-active"><span class="mini-lesson__n">03</span> Control Flow</div>
                    <div class="mini-lesson"><span class="mini-lesson__n">04</span> Loops</div>
                </div>
            </article>

            <article class="bento__cell reveal" data-delay="60">
                <div class="bento__icon bento__icon--amber">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <h3>Assignments with deadlines</h3>
                <p>Instructors set due dates and late rules. Students upload files, get graded, and read feedback without ever leaving the platform.</p>
            </article>

            <article class="bento__cell reveal" data-delay="120">
                <div class="bento__icon bento__icon--sage">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </div>
                <h3>Timed online exams</h3>
                <p>A live countdown on every exam. Auto-submits when time runs out. Grades instantly on the server, so results are same-day.</p>
            </article>

            <article class="bento__cell reveal" data-delay="180">
                <div class="bento__icon bento__icon--ink">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M4 10h16"/><path d="M9 4v16"/></svg>
                </div>
                <h3>Past papers &amp; notes</h3>
                <p>Every course keeps its own archive of CATs, midterms and finals — filterable by year and exam type.</p>
            </article>

            <article class="bento__cell bento__cell--wide reveal" data-delay="240">
                <div class="bento__icon bento__icon--coral">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-4 4 4 5-6"/></svg>
                </div>
                <h3>Progress you can see, grades you can trust</h3>
                <p>Live progress bars per course, an aggregated gradebook, and per-department statistics — so students, instructors and heads of department are always on the same page.</p>
                <div class="bento__bars">
                    <div class="mini-bar"><span>CS101</span><div class="mini-bar__track"><div style="width:100%"></div></div><b>100%</b></div>
                    <div class="mini-bar"><span>CS201</span><div class="mini-bar__track"><div style="width:68%"></div></div><b>68%</b></div>
                    <div class="mini-bar"><span>CS301</span><div class="mini-bar__track"><div style="width:34%"></div></div><b>34%</b></div>
                </div>
            </article>

            <article class="bento__cell reveal" data-delay="300">
                <div class="bento__icon bento__icon--amber">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3>Admin-approved access</h3>
                <p>New accounts sit in a review queue. Every department's roster stays verified and clean.</p>
            </article>
        </div>
    </div>
</section>

<!-- ============ STATS ============ -->
<section class="counter">
    <div class="counter__inner">
        <div class="counter__item reveal">
            <div class="counter__num" data-count="<?php echo (int)$__stats['students']; ?>">0</div>
            <div class="counter__label">Active students</div>
        </div>
        <div class="counter__item reveal" data-delay="80">
            <div class="counter__num" data-count="<?php echo (int)$__stats['instructors']; ?>">0</div>
            <div class="counter__label">Expert instructors</div>
        </div>
        <div class="counter__item reveal" data-delay="160">
            <div class="counter__num" data-count="<?php echo (int)$__stats['courses']; ?>">0</div>
            <div class="counter__label">Published courses</div>
        </div>
        <div class="counter__item reveal" data-delay="240">
            <div class="counter__num" data-count="<?php echo (int)$__stats['departments']; ?>">0</div>
            <div class="counter__label">Departments</div>
        </div>
    </div>
</section>

<!-- ============ DEPARTMENTS ============ -->
<section class="section section--cream" id="departments">
    <div class="section__inner">
        <header class="section__head reveal">
            <span class="kicker">Academic structure</span>
            <h2 class="section__title">A campus, not a pile of courses</h2>
            <p class="section__lead">
                Courses, instructors and students are grouped by department so deans
                and heads of department can see their faculty at a glance.
            </p>
        </header>

        <div class="depts">
            <?php foreach ($__departments as $i => $d): ?>
                <article class="dept reveal" data-delay="<?php echo $i * 60; ?>">
                    <div class="dept__head">
                        <span class="dept__badge"><?php echo __f($d['code']); ?></span>
                        <span class="dept__count"><?php echo (int)$d['course_count']; ?> course<?php echo (int)$d['course_count'] === 1 ? '' : 's'; ?></span>
                    </div>
                    <h3 class="dept__name"><?php echo __f($d['name']); ?></h3>
                    <p class="dept__desc"><?php echo __f($d['description']); ?></p>
                    <div class="dept__foot">
                        <span>Head</span>
                        <strong><?php echo __f($d['head_name'] ?: 'To be assigned'); ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ COURSES ============ -->
<section class="section" id="courses">
    <div class="section__inner">
        <header class="section__head reveal">
            <span class="kicker">Popular this term</span>
            <h2 class="section__title">What students are enrolling in</h2>
            <p class="section__lead">
                A live look at the busiest courses across the campus. The full catalogue
                is visible inside the portal with search and department filters.
            </p>
        </header>

        <div class="course-grid">
            <?php foreach ($__previewCourses as $i => $c): ?>
                <article class="course reveal" data-delay="<?php echo $i * 60; ?>">
                    <div class="course__head">
                        <span class="badge badge--level-<?php echo __f($c['level']); ?>"><?php echo __f(ucfirst($c['level'])); ?></span>
                        <span class="course__code"><?php echo __f($c['code']); ?></span>
                    </div>
                    <h3 class="course__title"><?php echo __f($c['title']); ?></h3>
                    <p class="course__desc"><?php echo __f(mb_substr($c['description'] ?? '', 0, 130)); ?><?php echo mb_strlen($c['description'] ?? '') > 130 ? '…' : ''; ?></p>
                    <dl class="course__meta">
                        <div><dt>Instructor</dt><dd><?php echo __f($c['instructor_name']); ?></dd></div>
                        <div><dt>Duration</dt><dd><?php echo (int)$c['duration_hours']; ?> hours</dd></div>
                        <div><dt>Enrolled</dt><dd><?php echo (int)$c['enrolled']; ?> students</dd></div>
                    </dl>
                    <div class="course__foot"><?php echo __f($c['dept_name'] ?: 'General'); ?></div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="center reveal">
            <button class="btn btn--primary btn--lg" type="button" data-portal="login">
                Sign in to browse the full catalogue
            </button>
        </div>
    </div>
</section>

<!-- ============ HOW IT WORKS ============ -->
<section class="section section--dark" id="how">
    <div class="section__inner">
        <header class="section__head section__head--light reveal">
            <span class="kicker kicker--light">Getting started</span>
            <h2 class="section__title">From application to graduation in four steps</h2>
            <p class="section__lead section__lead--light">
                Most applications are reviewed within one working day.
            </p>
        </header>

        <ol class="steps">
            <li class="step reveal">
                <span class="step__num">01</span>
                <h3>Apply for access</h3>
                <p>Create an account as a student or instructor. Choose your department and submit the form.</p>
            </li>
            <li class="step reveal" data-delay="80">
                <span class="step__num">02</span>
                <h3>Get approved</h3>
                <p>An administrator reviews your application. You'll be notified the moment your account is activated.</p>
            </li>
            <li class="step reveal" data-delay="160">
                <span class="step__num">03</span>
                <h3>Enrol in courses</h3>
                <p>Browse the catalogue, filter by department, and enrol in the courses that match your programme.</p>
            </li>
            <li class="step reveal" data-delay="240">
                <span class="step__num">04</span>
                <h3>Learn &amp; get graded</h3>
                <p>Watch lessons, submit assignments, sit online exams, and track your progress in real time.</p>
            </li>
        </ol>
    </div>
</section>

<!-- ============ TESTIMONIALS ============ -->
<section class="section">
    <div class="section__inner">
        <header class="section__head reveal">
            <span class="kicker">From the campus</span>
            <h2 class="section__title">What students and staff say</h2>
        </header>

        <div class="quotes">
            <figure class="quote reveal">
                <div class="quote__mark">"</div>
                <blockquote>Having all my assignments, notes and past papers in one place has completely changed how I revise. I can see exactly where I am in every course.</blockquote>
                <figcaption>
                    <span class="quote__avatar" style="--bg:#b8543c">JW</span>
                    <span>
                        <strong>Jane Wanjiku</strong>
                        <em>Computer Science, Year 2</em>
                    </span>
                </figcaption>
            </figure>

            <figure class="quote reveal" data-delay="80">
                <div class="quote__mark">"</div>
                <blockquote>The approval workflow means I only ever see verified students on my roster. Grading is fast, and feedback reaches them instantly.</blockquote>
                <figcaption>
                    <span class="quote__avatar" style="--bg:#6b8a63">AM</span>
                    <span>
                        <strong>Dr. Alice Mwangi</strong>
                        <em>Lecturer, Computer Science</em>
                    </span>
                </figcaption>
            </figure>

            <figure class="quote reveal" data-delay="160">
                <div class="quote__mark">"</div>
                <blockquote>Setting up online exams with automatic grading saved us weeks of marking. Our CATs are now same-day results.</blockquote>
                <figcaption>
                    <span class="quote__avatar" style="--bg:#d99b34">BO</span>
                    <span>
                        <strong>Prof. Brian Otieno</strong>
                        <em>Head of Department, IT</em>
                    </span>
                </figcaption>
            </figure>
        </div>
    </div>
</section>

<!-- ============ FAQ ============ -->
<section class="section section--cream" id="faq">
    <div class="section__inner">
        <header class="section__head reveal">
            <span class="kicker">Questions</span>
            <h2 class="section__title">Frequently asked</h2>
        </header>

        <div class="faq">
            <details class="faq__item reveal" open>
                <summary>How do I get an account?</summary>
                <p>Click <strong>Get started</strong> and fill in the registration form. Choose your role (student or instructor) and your department. Your application goes to the administrator for review.</p>
            </details>
            <details class="faq__item reveal">
                <summary>Why does my account need approval?</summary>
                <p>To keep rosters accurate and secure, every new account is reviewed by an administrator before it can sign in. You'll be notified once your account is activated.</p>
            </details>
            <details class="faq__item reveal">
                <summary>Can I enrol in courses from other departments?</summary>
                <p>Yes. While your primary department is used for reporting and admissions, you can enrol in any published course across the campus.</p>
            </details>
            <details class="faq__item reveal">
                <summary>What happens if I miss an assignment deadline?</summary>
                <p>Each assignment has its own late-submission policy set by the instructor. If late work is not allowed, the submission form is disabled once the deadline passes.</p>
            </details>
            <details class="faq__item reveal">
                <summary>How do online exams work?</summary>
                <p>When you open an exam, a countdown starts based on the instructor's configured duration. Your answers are auto-submitted when time runs out, and you see your score immediately.</p>
            </details>
            <details class="faq__item reveal">
                <summary>Can instructors upload their own teaching materials?</summary>
                <p>Absolutely. Instructors can add lessons, video links, downloadable notes, past papers and assignments to every course they own.</p>
            </details>
        </div>
    </div>
</section>

<!-- ============ FINAL CTA ============ -->
<section class="cta">
    <div class="cta__inner reveal">
        <h2>Ready to join the campus?</h2>
        <p>Apply for access today — most applications are reviewed within one working day.</p>
        <div class="cta__actions">
            <button class="btn btn--primary btn--xl" type="button" data-portal="register">Apply for access</button>
            <button class="btn btn--glass btn--xl" type="button" data-portal="login">Sign in</button>
        </div>
    </div>
</section>

<!-- ============ FOOTER ============ -->
<footer class="foot">
    <div class="foot__inner">
        <div class="foot__brand">
            <span class="brand__mark">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3 L3 8 L12 13 L21 8 Z"/>
                    <path d="M3 13 L12 18 L21 13"/>
                </svg>
            </span>
            <div>
                <strong>EduSphere</strong>
                <p>A modern Learning Management System for schools, colleges and training institutes.</p>
            </div>
        </div>
        <div class="foot__col">
            <h4>Platform</h4>
            <ul>
                <li><a href="#features" data-scroll>Features</a></li>
                <li><a href="#courses" data-scroll>Courses</a></li>
                <li><a href="#departments" data-scroll>Departments</a></li>
                <li><a href="#how" data-scroll>How it works</a></li>
            </ul>
        </div>
        <div class="foot__col">
            <h4>Portal</h4>
            <ul>
                <li><a href="#" data-portal="login">Sign in</a></li>
                <li><a href="#" data-portal="register">Apply for access</a></li>
                <li><a href="#faq" data-scroll>Help &amp; FAQ</a></li>
            </ul>
        </div>
        <div class="foot__col">
            <h4>Contact</h4>
            <ul>
                <li>Main Campus, Mombasa</li>
                <li>registrar@edusphere.com</li>
                <li>+254 (0) 702 440 073</li>
            </ul>
        </div>
    </div>
    <div class="foot__bottom">
        <span>&copy; <?php echo date('Y'); ?> EduSphere. All rights reserved.</span>
        <span>Built for modern classrooms.</span>
    </div>
</footer>

<!-- ============ PORTAL MODAL ============ -->
<div class="portal" id="portal" aria-hidden="true">
    <div class="portal__backdrop" data-portal-close></div>
    <div class="portal__sheet" role="dialog" aria-modal="true">
        <header class="portal__head">
            <div class="portal__tabs">
                <button class="portal__tab is-active" data-portal-tab="login">Sign in</button>
                <button class="portal__tab" data-portal-tab="register">Register</button>
            </div>
            <button class="portal__close" type="button" aria-label="Close" data-portal-close>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6l-12 12"/></svg>
            </button>
        </header>
        <div class="portal__frame">
            <iframe id="portalFrame" title="EduSphere portal" src="about:blank" loading="lazy"></iframe>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    /* ---------- Nav scroll state + progress bar ---------- */
    const nav = document.getElementById('nav');
    const progress = document.getElementById('navProgress');

    function onScroll() {
        const y = window.scrollY;
        nav.classList.toggle('is-scrolled', y > 12);
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const pct = max > 0 ? Math.min(100, (y / max) * 100) : 0;
        progress.style.width = pct + '%';
    }
    document.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* ---------- Mobile nav toggle ---------- */
    const burger = document.getElementById('navBurger');
    burger.addEventListener('click', () => nav.classList.toggle('is-open'));
    document.querySelectorAll('.nav__links a, .nav__cta .btn').forEach(el => {
        el.addEventListener('click', () => nav.classList.remove('is-open'));
    });

    /* ---------- Smooth scroll for anchor links ---------- */
    document.querySelectorAll('[data-scroll]').forEach(a => {
        a.addEventListener('click', e => {
            const href = a.getAttribute('href');
            if (!href || !href.startsWith('#')) return;
            const target = document.querySelector(href);
            if (!target) return;
            e.preventDefault();
            window.scrollTo({ top: target.offsetTop - 70, behavior: 'smooth' });
        });
    });

    /* ---------- Reveal on scroll (IntersectionObserver) ---------- */
    const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const delay = parseInt(entry.target.dataset.delay || '0', 10);
                setTimeout(() => entry.target.classList.add('is-visible'), delay);
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('.reveal').forEach(el => io.observe(el));

    /* ---------- Animated counters ---------- */
    const counterIO = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const el = entry.target;
            const target = parseInt(el.dataset.count || '0', 10);
            const duration = 1400;
            const start = performance.now();
            (function tick(now) {
                const t = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - t, 3);
                el.textContent = Math.round(eased * target);
                if (t < 1) requestAnimationFrame(tick);
            })(start);
            counterIO.unobserve(el);
        });
    }, { threshold: 0.4 });
    document.querySelectorAll('[data-count]').forEach(el => counterIO.observe(el));

    /* ---------- Cursor glow (desktop only) ---------- */
    const glow = document.getElementById('cursorGlow');
    if (glow && window.matchMedia('(hover: hover)').matches) {
        window.addEventListener('mousemove', e => {
            glow.style.transform = `translate(${e.clientX}px, ${e.clientY}px)`;
        });
    } else if (glow) {
        glow.remove();
    }

    /* ---------- Portal modal (login/register) ---------- */
    const portal = document.getElementById('portal');
    const frame  = document.getElementById('portalFrame');
    const tabs   = portal.querySelectorAll('.portal__tab');
    const base   = <?php echo json_encode(SITE_URL); ?>;

    function openPortal(mode) {
        const url = base + '/pages/portal.php?mode=' + encodeURIComponent(mode);
        frame.src = url;
        tabs.forEach(t => t.classList.toggle('is-active', t.dataset.portalTab === mode));
        portal.classList.add('is-open');
        portal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closePortal() {
        portal.classList.remove('is-open');
        portal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        setTimeout(() => { frame.src = 'about:blank'; }, 250);
    }

    document.querySelectorAll('[data-portal]').forEach(el => {
        el.addEventListener('click', e => {
            e.preventDefault();
            openPortal(el.dataset.portal);
        });
    });
    portal.querySelectorAll('[data-portal-close]').forEach(el => el.addEventListener('click', closePortal));

    tabs.forEach(tab => tab.addEventListener('click', () => openPortal(tab.dataset.portalTab)));

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && portal.classList.contains('is-open')) closePortal();
    });

    /* Allow the iframe to signal success so the parent can redirect */
    window.addEventListener('message', e => {
        if (!e.data || e.data.type !== 'edusphere:portal-success') return;
        window.location.href = e.data.redirect || (base + '/pages/dashboard.php');
    });
})();
</script>
</body>
</html>