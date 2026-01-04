/**
 * SNN Edu User Meta - Video Enrollment Tracker
 *
 * Listens for video events and tracks user enrollment via REST API
 */

(function() {
    'use strict';

    // Check if config is available
    if (typeof snnEduUserMeta === 'undefined') {
        console.warn('SNN Edu User Meta: Configuration not found');
        return;
    }

    /**
     * Enroll user in a post via REST API
     */
    window.snnEduEnrollUser = function(postId) {
        if (!postId || !Number.isInteger(parseInt(postId))) {
            console.error('SNN Edu: Invalid post ID', postId);
            return Promise.reject('Invalid post ID');
        }

        const enrollmentData = {
            post_id: parseInt(postId)
        };

        return fetch(snnEduUserMeta.restUrl + 'enroll', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': snnEduUserMeta.nonce
            },
            body: JSON.stringify(enrollmentData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ SNN Edu: Successfully enrolled in post', postId);

                // Dispatch custom event for other scripts to listen
                document.dispatchEvent(new CustomEvent('snn_edu_enrolled', {
                    detail: {
                        post_id: postId,
                        enrolled_count: data.enrolled_count
                    }
                }));
            } else {
                console.log('ℹ️ SNN Edu:', data.message, postId);
            }
            return data;
        })
        .catch(error => {
            console.error('❌ SNN Edu: Enrollment failed', error);
            return { success: false, error: error.message };
        });
    };

    /**
     * Unenroll user from a post via REST API
     */
    window.snnEduUnenrollUser = function(postId) {
        if (!postId || !Number.isInteger(parseInt(postId))) {
            console.error('SNN Edu: Invalid post ID', postId);
            return Promise.reject('Invalid post ID');
        }

        const enrollmentData = {
            post_id: parseInt(postId)
        };

        return fetch(snnEduUserMeta.restUrl + 'unenroll', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': snnEduUserMeta.nonce
            },
            body: JSON.stringify(enrollmentData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ SNN Edu: Successfully unenrolled from post', postId);

                // Dispatch custom event
                document.dispatchEvent(new CustomEvent('snn_edu_unenrolled', {
                    detail: {
                        post_id: postId,
                        enrolled_count: data.enrolled_count
                    }
                }));
            } else {
                console.log('ℹ️ SNN Edu:', data.message, postId);
            }
            return data;
        })
        .catch(error => {
            console.error('❌ SNN Edu: Unenrollment failed', error);
            return { success: false, error: error.message };
        });
    };

    /**
     * Get all enrollments for current user
     */
    window.snnEduGetEnrollments = function() {
        return fetch(snnEduUserMeta.restUrl + 'enrollments', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': snnEduUserMeta.nonce
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('📚 SNN Edu: User enrollments', data);
            return data;
        })
        .catch(error => {
            console.error('❌ SNN Edu: Failed to get enrollments', error);
            return { success: false, error: error.message };
        });
    };

    /**
     * Check if user is enrolled in a specific post
     */
    window.snnEduIsEnrolled = function(postId) {
        return window.snnEduGetEnrollments()
            .then(data => {
                if (data.success && data.enrollments) {
                    return data.enrollments.includes(parseInt(postId));
                }
                return false;
            });
    };

    // Log initialization
    console.log('🎓 SNN Edu User Meta Tracker initialized for user:', snnEduUserMeta.userId);

})();
