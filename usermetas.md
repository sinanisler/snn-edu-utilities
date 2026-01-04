# User Meta Structure Plan

## Overview
This document outlines the plan for storing course enrollment and lesson completion data in WordPress user meta.

## User Meta Keys

### 1. Course Enrollment
**Meta Key:** `snn_edu_enrollments`

**Structure:** Array of enrolled course IDs
```php
[
    123,  // Course Post ID
    456,  // Course Post ID
    789   // Course Post ID
]
```

**Functions:**
- `snn_edu_enroll_user($user_id, $course_id)` - Enroll user in a course
- `snn_edu_unenroll_user($user_id, $course_id)` - Remove user from a course
- `snn_edu_is_user_enrolled($user_id, $course_id)` - Check if user is enrolled
- `snn_edu_get_user_enrollments($user_id)` - Get all user enrollments

**Implementation:**
```php
// Enroll user
function snn_edu_enroll_user($user_id, $course_id) {
    $enrollments = get_user_meta($user_id, 'snn_edu_enrollments', true);
    if (!is_array($enrollments)) {
        $enrollments = [];
    }

    if (!in_array($course_id, $enrollments)) {
        $enrollments[] = $course_id;
        update_user_meta($user_id, 'snn_edu_enrollments', $enrollments);

        // Store enrollment date
        update_user_meta($user_id, 'snn_edu_enrollment_date_' . $course_id, current_time('mysql'));

        return true;
    }
    return false;
}

// Check enrollment
function snn_edu_is_user_enrolled($user_id, $course_id) {
    $enrollments = get_user_meta($user_id, 'snn_edu_enrollments', true);
    return is_array($enrollments) && in_array($course_id, $enrollments);
}

// Get all enrollments
function snn_edu_get_user_enrollments($user_id) {
    $enrollments = get_user_meta($user_id, 'snn_edu_enrollments', true);
    return is_array($enrollments) ? $enrollments : [];
}

// Unenroll user
function snn_edu_unenroll_user($user_id, $course_id) {
    $enrollments = get_user_meta($user_id, 'snn_edu_enrollments', true);
    if (is_array($enrollments)) {
        $key = array_search($course_id, $enrollments);
        if ($key !== false) {
            unset($enrollments[$key]);
            update_user_meta($user_id, 'snn_edu_enrollments', array_values($enrollments));
            delete_user_meta($user_id, 'snn_edu_enrollment_date_' . $course_id);
            return true;
        }
    }
    return false;
}
```

---

### 2. Lesson Completion
**Meta Key:** `snn_edu_completed_lessons`

**Structure:** Associative array with course IDs as keys and arrays of completed lesson IDs as values
```php
[
    123 => [10, 11, 12],  // Course ID => [Lesson IDs]
    456 => [20, 21],
    789 => [30, 31, 32, 33]
]
```

**Functions:**
- `snn_edu_mark_lesson_complete($user_id, $course_id, $lesson_id)` - Mark a lesson as complete
- `snn_edu_is_lesson_complete($user_id, $course_id, $lesson_id)` - Check if lesson is complete
- `snn_edu_get_completed_lessons($user_id, $course_id)` - Get completed lessons for a course
- `snn_edu_get_course_progress($user_id, $course_id)` - Calculate course completion percentage

**Implementation:**
```php
// Mark lesson complete
function snn_edu_mark_lesson_complete($user_id, $course_id, $lesson_id) {
    $completed = get_user_meta($user_id, 'snn_edu_completed_lessons', true);
    if (!is_array($completed)) {
        $completed = [];
    }

    if (!isset($completed[$course_id])) {
        $completed[$course_id] = [];
    }

    if (!in_array($lesson_id, $completed[$course_id])) {
        $completed[$course_id][] = $lesson_id;
        update_user_meta($user_id, 'snn_edu_completed_lessons', $completed);

        // Store completion timestamp
        update_user_meta($user_id, 'snn_edu_lesson_completed_' . $lesson_id, current_time('mysql'));

        return true;
    }
    return false;
}

// Check if lesson is complete
function snn_edu_is_lesson_complete($user_id, $course_id, $lesson_id) {
    $completed = get_user_meta($user_id, 'snn_edu_completed_lessons', true);
    return isset($completed[$course_id]) && in_array($lesson_id, $completed[$course_id]);
}

// Get completed lessons for a course
function snn_edu_get_completed_lessons($user_id, $course_id) {
    $completed = get_user_meta($user_id, 'snn_edu_completed_lessons', true);
    return isset($completed[$course_id]) ? $completed[$course_id] : [];
}

// Calculate course progress
function snn_edu_get_course_progress($user_id, $course_id) {
    // Get total lessons in course
    $total_lessons = snn_edu_get_course_lesson_count($course_id);

    if ($total_lessons === 0) {
        return 0;
    }

    // Get completed lessons
    $completed_lessons = snn_edu_get_completed_lessons($user_id, $course_id);
    $completed_count = count($completed_lessons);

    // Calculate percentage
    return round(($completed_count / $total_lessons) * 100, 2);
}
```

---

## Additional Meta Keys

### Enrollment Date
**Meta Key:** `snn_edu_enrollment_date_{course_id}`
**Type:** String (MySQL datetime)
**Example:** `2026-01-04 14:30:00`

### Lesson Completion Timestamp
**Meta Key:** `snn_edu_lesson_completed_{lesson_id}`
**Type:** String (MySQL datetime)
**Example:** `2026-01-04 15:45:00`

---

## Usage Examples

### Enrolling a User
```php
$user_id = get_current_user_id();
$course_id = 123;
snn_edu_enroll_user($user_id, $course_id);
```

### Checking Enrollment
```php
if (snn_edu_is_user_enrolled($user_id, $course_id)) {
    echo "User is enrolled!";
}
```

### Marking Lesson Complete
```php
$lesson_id = 10;
snn_edu_mark_lesson_complete($user_id, $course_id, $lesson_id);
```

### Getting Progress
```php
$progress = snn_edu_get_course_progress($user_id, $course_id);
echo "Course is {$progress}% complete";
```

---

## Data Migration Considerations

If you have existing enrollment data in a custom table or different structure:
1. Create a migration script to transfer data to user meta
2. Verify data integrity after migration
3. Keep old data as backup until confirmed working
4. Update all functions to use new user meta structure

---

## Performance & Scaling Considerations

### When User Meta Works Well
**Good for sites with:**
- Up to 10,000 users
- Up to 100 courses
- Individual user-focused queries (dashboards, progress tracking)
- Simple WordPress sites without complex reporting needs

**Advantages:**
- Native WordPress functionality
- No custom table management
- Easy to implement and maintain
- Works with existing WordPress user queries
- Automatic cleanup when users are deleted

---

### Scaling Problems with User Meta

#### 1. Serialized Data Issues
- WordPress stores arrays as serialized strings
- Can't efficiently query "all users who completed lesson X"
- `wp_usermeta` table grows very large
- Every lesson completion updates the entire array (inefficient)

#### 2. Query Performance Degradation
**Bad queries at scale:**
```php
// This becomes VERY slow with 10,000+ users
$args = array(
    'meta_query' => array(
        array(
            'key' => 'snn_edu_enrollments',
            'value' => '123',
            'compare' => 'LIKE'
        )
    )
);
$user_query = new WP_User_Query($args);
```

#### 3. Reporting Limitations
- Can't efficiently generate reports like:
  - "Show all users enrolled in course X"
  - "How many users completed lesson Y"
  - "Average completion rate across all courses"
  - "Most popular courses"

#### 4. Memory Issues
- Loading large serialized arrays for users with many enrollments
- Bulk operations load all user meta into memory

---

### Hybrid Approach (Recommended for Growth)

Use **custom database tables** for relational data with **user meta for cached summaries**.

#### Custom Tables Structure

**Table: `wp_snn_edu_enrollments`**
```sql
CREATE TABLE wp_snn_edu_enrollments (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    course_id BIGINT(20) UNSIGNED NOT NULL,
    enrolled_date DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY user_course (user_id, course_id),
    KEY course_id (course_id),
    KEY user_id (user_id),
    KEY status (status)
);
```

**Table: `wp_snn_edu_lesson_progress`**
```sql
CREATE TABLE wp_snn_edu_lesson_progress (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    course_id BIGINT(20) UNSIGNED NOT NULL,
    lesson_id BIGINT(20) UNSIGNED NOT NULL,
    completed_date DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY user_lesson (user_id, lesson_id),
    KEY course_id (course_id),
    KEY user_id (user_id),
    KEY lesson_id (lesson_id)
);
```

**Why This Is Better:**
- Proper indexing for fast queries
- Can efficiently query in any direction
- No serialized data issues
- Supports complex reporting
- Scales to millions of records

**Cache Summary in User Meta:**
```php
// Store quick-access data in user meta for dashboard
update_user_meta($user_id, 'snn_edu_cache_enrolled_count', 5);
update_user_meta($user_id, 'snn_edu_cache_completed_count', 23);
update_user_meta($user_id, 'snn_edu_cache_last_activity', '2026-01-04 15:30:00');
```

---

### Migration Path

#### Phase 1: Start with User Meta (Current)
- Quick to implement
- Good for MVP and testing
- Easy to understand

#### Phase 2: Add Custom Tables When You Hit:
- More than 5,000 active users
- More than 50 courses
- Need for reporting/analytics
- Slow query performance
- Need to query "all users in course X"

#### Phase 3: Migration Script
```php
function snn_edu_migrate_to_custom_tables() {
    global $wpdb;

    // Get all users with enrollments
    $users = get_users(array(
        'meta_key' => 'snn_edu_enrollments',
        'fields' => 'ID'
    ));

    foreach ($users as $user_id) {
        // Migrate enrollments
        $enrollments = get_user_meta($user_id, 'snn_edu_enrollments', true);
        foreach ($enrollments as $course_id) {
            $enrolled_date = get_user_meta($user_id, 'snn_edu_enrollment_date_' . $course_id, true);

            $wpdb->insert(
                $wpdb->prefix . 'snn_edu_enrollments',
                array(
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'enrolled_date' => $enrolled_date,
                    'status' => 'active'
                )
            );
        }

        // Migrate lesson progress
        $completed = get_user_meta($user_id, 'snn_edu_completed_lessons', true);
        foreach ($completed as $course_id => $lessons) {
            foreach ($lessons as $lesson_id) {
                $completed_date = get_user_meta($user_id, 'snn_edu_lesson_completed_' . $lesson_id, true);

                $wpdb->insert(
                    $wpdb->prefix . 'snn_edu_lesson_progress',
                    array(
                        'user_id' => $user_id,
                        'course_id' => $course_id,
                        'lesson_id' => $lesson_id,
                        'completed_date' => $completed_date
                    )
                );
            }
        }
    }
}
```

---

### Performance Comparison

#### User Meta Approach
```php
// Get all users in a course (SLOW at scale)
$args = array(
    'meta_query' => array(
        array(
            'key' => 'snn_edu_enrollments',
            'value' => serialize($course_id),
            'compare' => 'LIKE'
        )
    )
);
$users = get_users($args);
// 10,000 users: ~5-15 seconds
```

#### Custom Table Approach
```php
// Get all users in a course (FAST)
global $wpdb;
$users = $wpdb->get_results($wpdb->prepare(
    "SELECT user_id FROM {$wpdb->prefix}snn_edu_enrollments
     WHERE course_id = %d AND status = 'active'",
    $course_id
));
// 10,000 users: ~0.01-0.1 seconds
```

---

### Recommendation

**Start with User Meta** (your current plan) because:
- Faster to implement
- Good for early stage
- Easy to test and iterate
- You can always migrate later

**Plan to migrate to Custom Tables when:**
- You reach 5,000+ users
- You need reporting features
- Queries become noticeably slow
- You need to scale beyond a single site

The migration path is straightforward and you can run both systems in parallel during transition.

---

## Future Enhancements

- Quiz scores and attempts
- Certificate generation data
- Course ratings and reviews
- Last accessed lesson/timestamp
- Course completion date
- Learning streaks/statistics
