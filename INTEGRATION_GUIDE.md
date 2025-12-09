# Integration Guide - New Features

## Complete Implementation Steps

### Step 1: Database Setup

Run the SQL migration script:

```bash
mysql -u root -p library_db < database_migrations_new_features.sql
```

This creates:
- `uploaded_notes` table
- `book_suggestions` table
- Ensures `due_date` column exists in `issued_books`

### Step 2: File Structure

All new files have been created. Here's what was added:

#### API Endpoints:
- `/api/get_login_notifications.php` - Fetches login notifications
- `/api/request_book.php` - One-click book request handler

#### JavaScript Files:
- `/assets/js/login_notifications.js` - Login popup modal handler
- `/assets/js/book_request.js` - One-click request button handler

#### PHP Pages:
- `/student/upload_notes.php` - Upload notes/PDFs (also accessible to teachers)
- `/student/suggest_book.php` - Book suggestions (also accessible to teachers)
- `/admin/manage_uploads.php` - Admin approval for uploads
- `/admin/manage_suggestions.php` - Admin approval for suggestions

### Step 3: Modified Files

#### Updated Files:
1. `includes/footer.php` - Added JS includes for logged-in users
2. `includes/navbar.php` - Added menu links for new features
3. `student/student_dashboard.php` - Added due date column
4. `teacher/teacher_dashboard.php` - Added due date column
5. `student/available_books.php` - Changed Request button to AJAX
6. `teacher/available_books.php` - Changed Request button to AJAX

### Step 4: Features Overview

#### 1. Login Popup Notification ✅
- Shows once per session after login
- Displays overdue fines, upcoming due dates, admin messages
- Uses sessionStorage to prevent multiple popups
- Integrated in footer.php (auto-loads for logged-in users)

#### 2. Return Date in Recent Issued Books ✅
- Added "Due Date" column to both student and teacher dashboards
- Shows calculated due date if not explicitly set
- Displays "Not returned" for books still issued

#### 3. One-Click Request Button ✅
- AJAX-based request (no page reload)
- Auto-fills department from book data
- Shows success modal after request
- Includes funny error messages for limit reached

#### 4. Book Issue Limits with Funny Notifications ✅
- Integrated in `api/request_book.php`
- Shows random funny messages when limit reached
- Messages like:
  - "No more books allowed! Your bag is already heavier than your future 😆"
  - "Return some books first, book hoarder! 📚😜"
  - "Library says: bas karo! pehle purane return karo 😄"

#### 5. Upload Notes/PDFs ✅
- Students and teachers can upload:
  - Handwritten notes (JPG, PNG)
  - PDF files (max 500 MB)
- Files stored in `/uploads/notes/`
- Status: Pending → Admin approves/rejects
- Admin can view and manage in `/admin/manage_uploads.php`

#### 6. Book Suggestions ✅
- Students and teachers can suggest new books
- Fields: Title, Author, Optional Note
- Status: Pending → Admin approves/rejects
- Admin can manage in `/admin/manage_suggestions.php`

### Step 5: Testing Checklist

- [ ] Run database migration
- [ ] Test login popup (should show once per session)
- [ ] Check due date column in dashboards
- [ ] Test one-click request button
- [ ] Test limit reached message (borrow max books, then try to request)
- [ ] Upload a note/PDF file
- [ ] Submit a book suggestion
- [ ] As admin, approve/reject uploads
- [ ] As admin, approve/reject suggestions

### Step 6: Security Notes

- All forms use CSRF protection
- File uploads validated (type and size)
- Prepared statements used for all queries
- User authentication checked on all pages
- File paths sanitized

### Step 7: File Permissions

Ensure upload directory is writable:

```bash
mkdir -p uploads/notes
chmod 775 uploads/notes
```

### Step 8: Configuration

No additional configuration needed. All features use existing:
- Database connection from `config.php`
- Session management
- CSRF tokens
- User authentication

## Troubleshooting

### Login popup not showing?
- Check browser console for errors
- Verify `sessionStorage` is enabled
- Check that user is logged in (session exists)

### Request button not working?
- Check browser console for errors
- Verify CSRF token is present
- Check API endpoint is accessible

### Uploads failing?
- Check file permissions on `uploads/notes/`
- Verify file size limit (500 MB)
- Check PHP upload limits in php.ini

### Database errors?
- Ensure migration script ran successfully
- Check table structure matches expected schema
- Verify foreign key constraints

## Support

All features are integrated and ready to use. If you encounter issues:
1. Check error logs in `storage/logs/app.log`
2. Verify database structure
3. Check file permissions
4. Review browser console for JS errors

