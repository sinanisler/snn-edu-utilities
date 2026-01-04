# SNN Edu User Meta - Usage Examples

This feature enables tracking of user video enrollments via REST API and JavaScript events.

## 🚀 Quick Start

### 1. Enable the Feature
Go to **Settings → SNN Edu Utilities** and check **"Enable User Video Enrollment Tracking"**

### 2. Use the Shortcode (Easiest Method)
Add this shortcode to any post/page where you have videos:

```php
[snn_video_tracker]
```

This will automatically enroll users when video events fire.

**Shortcode Parameters:**
- `event`: Which event to track - `started` or `completed` (default: `completed`)
- `auto`: Auto-enroll on event - `true` or `false` (default: `true`)

**Examples:**
```php
// Track when video is completed (default)
[snn_video_tracker]

// Track when video starts
[snn_video_tracker event="started"]

// Display tracker but don't auto-enroll (manual control)
[snn_video_tracker auto="false"]
```

---

## 📡 REST API Endpoints

All endpoints require users to be logged in. Only accepts integer post IDs.

### Enroll User
```javascript
POST /wp-json/snn-edu/v1/enroll

// Body
{
    "post_id": 123
}

// Response
{
    "success": true,
    "message": "Successfully enrolled",
    "post_id": 123,
    "enrolled_count": 5
}
```

### Unenroll User
```javascript
POST /wp-json/snn-edu/v1/unenroll

// Body
{
    "post_id": 123
}

// Response
{
    "success": true,
    "message": "Successfully unenrolled",
    "post_id": 123,
    "enrolled_count": 4
}
```

### Get All Enrollments
```javascript
GET /wp-json/snn-edu/v1/enrollments

// Response
{
    "success": true,
    "enrollments": [123, 456, 789],
    "count": 3
}
```

---

## 🎯 JavaScript API

The plugin provides global JavaScript functions for easy enrollment tracking:

### Available Functions

#### 1. Enroll User
```javascript
snnEduEnrollUser(postId)
    .then(response => {
        console.log('Enrolled!', response);
    });
```

#### 2. Unenroll User
```javascript
snnEduUnenrollUser(postId)
    .then(response => {
        console.log('Unenrolled!', response);
    });
```

#### 3. Get All Enrollments
```javascript
snnEduGetEnrollments()
    .then(data => {
        console.log('My enrollments:', data.enrollments);
        console.log('Total:', data.count);
    });
```

#### 4. Check If Enrolled
```javascript
snnEduIsEnrolled(postId)
    .then(isEnrolled => {
        if (isEnrolled) {
            console.log('Already enrolled!');
        }
    });
```

---

## 🎬 Video Event Integration

The system listens for these custom events:

### Listen for Video Started
```javascript
document.addEventListener('snn_video_started', function(event) {
    console.log('Video started!');
    console.log('Page URL:', event.detail.url);
    console.log('Video URL:', event.detail.videoUrl);
    console.log('Player ID:', event.detail.elementId);
    console.log('Post ID:', event.detail.post_id);

    // Auto-enroll user when video starts
    snnEduEnrollUser(event.detail.post_id);
});
```

### Listen for Video Completed
```javascript
document.addEventListener('snn_video_completed', function(event) {
    console.log('Video completed!');
    console.log('Post ID:', event.detail.post_id);

    // Auto-enroll user when video completes
    snnEduEnrollUser(event.detail.post_id);
});
```

### Listen for Enrollment Events
```javascript
// User enrolled successfully
document.addEventListener('snn_edu_enrolled', function(event) {
    console.log('User enrolled in post:', event.detail.post_id);
    console.log('Total enrollments:', event.detail.enrolled_count);

    // Do something - show message, redirect, update UI, etc.
});

// User unenrolled successfully
document.addEventListener('snn_edu_unenrolled', function(event) {
    console.log('User unenrolled from post:', event.detail.post_id);
    console.log('Total enrollments:', event.detail.enrolled_count);
});
```

---

## 🔧 Complete Implementation Example

### In your theme or custom plugin:

```javascript
(function() {
    'use strict';

    // Example 1: Auto-enroll when video completes
    document.addEventListener('snn_video_completed', function(event) {
        const postId = event.detail.post_id;

        snnEduEnrollUser(postId).then(response => {
            if (response.success) {
                // Show success message
                alert('Congratulations! You completed this course.');

                // Optionally redirect to next course
                // window.location.href = '/next-course';
            }
        });
    });

    // Example 2: Track when video starts
    document.addEventListener('snn_video_started', function(event) {
        const postId = event.detail.post_id;

        // Check if already enrolled
        snnEduIsEnrolled(postId).then(enrolled => {
            if (!enrolled) {
                // First time watching - enroll them
                snnEduEnrollUser(postId);
            }
        });
    });

    // Example 3: Show enrollment count
    document.addEventListener('snn_edu_enrolled', function(event) {
        const count = event.detail.enrolled_count;

        // Update UI to show enrollment count
        const badge = document.querySelector('.enrollment-badge');
        if (badge) {
            badge.textContent = count + ' courses enrolled';
        }
    });

    // Example 4: Custom unenroll button
    const unenrollButtons = document.querySelectorAll('.unenroll-btn');
    unenrollButtons.forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.dataset.postId;

            if (confirm('Are you sure you want to unenroll?')) {
                snnEduUnenrollUser(postId).then(response => {
                    if (response.success) {
                        alert('Successfully unenrolled');
                        location.reload();
                    }
                });
            }
        });
    });

})();
```

---

## 👤 Admin Features

### User Profile Meta Box

When viewing a user's profile in WordPress admin (Users → Edit User), you'll see a **"Course Enrollments"** meta box showing:

- Total enrollment count
- Table with all enrolled courses:
  - Post ID
  - Post Title (clickable)
  - Post Type
  - Post Status
  - View/Edit links

This is perfect for debugging and support.

---

## 🔒 Security Features

- ✅ Only works for logged-in users
- ✅ Only accepts integer post IDs (sanitized with `absint()`)
- ✅ REST API uses WordPress nonce verification
- ✅ Validates post existence before enrollment
- ✅ No external data accepted
- ✅ All responses are properly sanitized

---

## 💾 Database Structure

User meta key: `snn_edu_enrolled_posts`

Data structure: Array of integers (post IDs)

Example:
```php
// User meta for user ID 5
get_user_meta(5, 'snn_edu_enrolled_posts', true);

// Returns:
[123, 456, 789, 101112]
```

---

## 🎨 Shortcode Styling

The `[snn_video_tracker]` shortcode includes default styling. To customize:

```css
/* Override default styles */
.snn-edu-tracker {
    background: your-color !important;
    border-color: your-color !important;
}

.snn-edu-tracker-text {
    color: your-color !important;
}
```

---

## 🐛 Debugging

Enable browser console to see enrollment activity:

```javascript
// The tracker logs all actions:
// ✅ SNN Edu: Successfully enrolled in post 123
// ℹ️ SNN Edu: Already enrolled 123
// ❌ SNN Edu: Enrollment failed
```

---

## ❓ FAQ

**Q: Does this work for non-logged-in users?**
A: No, users must be logged in. Non-logged-in users will see "Please log in to track your progress."

**Q: Can I use this for other content types besides videos?**
A: Yes! While designed for videos, you can track any post type by calling `snnEduEnrollUser(postId)` manually.

**Q: What happens if I delete a post that users are enrolled in?**
A: The post ID stays in the user meta but shows as "Post not found (may be deleted)" in the admin meta box.

**Q: Can I export enrollment data?**
A: Yes, the data is stored in user meta (`snn_edu_enrolled_posts`). You can query it with standard WordPress functions or use the REST API endpoint.

**Q: Is this multisite compatible?**
A: Yes, each site in a multisite network will have its own enrollment data.

---

## 🚦 Next Steps

1. Enable the feature in Settings
2. Add `[snn_video_tracker]` to your posts
3. Fire video events: `snn_video_started` or `snn_video_completed`
4. View enrollments in user profiles
5. Customize with JavaScript API as needed

For support: https://github.com/sinanisler/snn-edu-utilities/issues
