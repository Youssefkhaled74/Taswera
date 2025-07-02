Product Requirements Document (PRD) for Taswera

1. Introduction

The "Taswera" project is a web-based photo management system designed for events or locations where multiple photographers capture images of users. Each user is assigned a unique barcode by branch staff, which links their photos. Users access their photos via a touch screen by entering their barcode and phone number, select photos for printing with optional frames or filters, and proceed to checkout. The system operates across multiple branches, each with a local database, synchronized with a central database for aggregated reporting.

2. Objectives





Enable users to access their photos using a barcode and phone number without traditional accounts.



Allow staff to register users, assign barcodes, and upload photos linked to those barcodes.



Provide an intuitive touch-screen interface for users to view, select, and customize photos.



Manage branch-specific operations with local databases and synchronize data with a central system for performance tracking.



Support checkout with payment processing and send photo links via WhatsApp.

3. Key Features





User Registration and Barcode Assignment: Staff register users and assign unique barcodes.



Photo Upload: Photographers upload photos linked to user barcodes.



Photo Access and Selection: Users input their barcode and phone number on a touch screen to view and select photos, with options for frames or filters.



Order and Payment Processing: Users select a package, and staff process payments and print photos.



WhatsApp Integration: Send photo links to users via WhatsApp, valid for 24 hours.



Branch Management and Synchronization: Each branch has a local database, synchronized with a central system every 10 minutes for sales and order tracking.

4. Detailed Requirements

4.1 User Registration and Barcode Assignment





Description: Staff register users at a branch by entering their phone number and generating a unique barcode.



Details:





Staff interface to input the user’s phone number and assign a barcode (e.g., printed on a bracelet).



Barcodes are unique per user and stored in the branch’s local database.



Users are identified by their barcode and phone number; no traditional accounts are required.



Acceptance Criteria:





Staff can generate and assign a barcode to a user.



The system validates the phone number format.



The barcode is stored with the user’s phone number in the local database.

4.2 Photo Upload





Description: Photographers upload photos associated with a user’s barcode.



Details:





Staff interface for uploading multiple photos, each linked to a specific barcode.



Photos are stored locally or in cloud storage (e.g., AWS S3), with metadata recorded in the local database.



Only authorized staff (with role = 'staff') can upload photos.



Acceptance Criteria:





Photos are successfully uploaded and linked to the correct barcode.



File paths are stored in the database with the uploader’s ID.



Uploads are optimized (e.g., resized to reduce storage needs).

4.3 Photo Access and Selection





Description: Users access their photos via a touch screen by entering their barcode and phone number.



Details:





The touch screen (React-based web interface) has two input fields: barcode and phone number.



Upon valid input, the system retrieves and displays all photos linked to the barcode.



Users can select photos, apply frames or filters, and choose a package.



Acceptance Criteria:





The system validates the barcode and phone number against the local database.



Photos are displayed only if the barcode and phone number match.



Users can preview photos and select customization options.

4.4 Order and Payment Processing





Description: Users proceed to checkout to pay for selected photos and packages.



Details:





After selecting photos and a package, users are directed to a cashier interface.



The cashier scans the barcode to retrieve the order, processes payment, and prints selected photos.



Payment methods include cash or credit.



A link to the edited photos (and all shoots) is sent via WhatsApp, valid for 24 hours.



Acceptance Criteria:





Orders are created with selected photos, frames, filters, and package details.



Payments are recorded with the order ID and amount.



Photos are printed, and a WhatsApp link is sent successfully.

4.5 Branch Management and Synchronization





Description: Each branch operates independently with a local database, synchronized with a central database.



Details:





Local databases store user, photo, package, order, and payment data.



Synchronization occurs every 10 minutes, sending total sales and order counts to the central database.



The central database tracks branch performance and aggregates sales data.



Acceptance Criteria:





Local databases operate independently for each branch.



Synchronization updates the central database with accurate sales and order data.



Admins can view branch performance via a central dashboard.

4.6 Central Dashboard





Description: A cloud-based dashboard for admins to monitor branch performance.



Details:





Displays total sales, orders, and user counts per branch.



Shows synchronization logs and aggregated sales data.



Accessible only to authorized admins.



Acceptance Criteria:





Dashboard displays real-time data from sync logs.



Admins can filter data by branch, date, or other metrics.



Access is restricted to authenticated admins.

5. Database Design

5.1 Branch Database (Local)





users:





id: Primary key



barcode: String, unique



phone_number: String



role: String (e.g., 'user', 'staff')



created_at, updated_at: Timestamps



photos:





id: Primary key



user_id: Foreign key to users (cascade on delete)



file_path: String (path to photo file)



uploaded_by: Foreign key to users (set null on delete, for staff)



created_at, updated_at: Timestamps



packages:





id: Primary key



name: String



price: Decimal



photo_count: Integer



description: Text



created_at, updated_at: Timestamps



orders:





id: Primary key



user_id: Foreign key to users (cascade on delete)



total_price: Decimal



status: String (e.g., 'pending', 'completed')



created_at, updated_at: Timestamps



order_items:





id: Primary key



order_id: Foreign key to orders (cascade on delete)



photo_id: Foreign key to photos (cascade on delete)



frame: String, nullable



filter: String, nullable



created_at, updated_at: Timestamps



payments:





id: Primary key



order_id: Foreign key to orders (cascade on delete)



amount: Decimal



method: String (e.g., 'cash', 'credit')



paid_at: Timestamp



created_at, updated_at: Timestamps



sync_log:





id: Primary key



synced_at: Timestamp



total_sales: Decimal



total_orders: Integer



created_at, updated_at: Timestamps

5.2 Central Database





branches:





id: Primary key



name: String



location: String



created_at, updated_at: Timestamps



sync_logs:





id: Primary key



branch_id: Foreign key to branches (cascade on delete)



synced_at: Timestamp



total_sales: Decimal



total_orders: Integer



created_at, updated_at: Timestamps



admins:





id: Primary key



name: String



email: String, unique



password: String



created_at, updated_at: Timestamps



aggregated_sales:





id: Primary key



branch_id: Foreign key to branches (cascade on delete)



date: Date (aggregation period)



total_sales: Decimal



created_at, updated_at: Timestamps

6. User Flow





Registration:





Staff registers a user by entering their phone number and generating a barcode.



The user receives the barcode (e.g., on a bracelet).



Photo Capture and Upload:





Photographers take photos and upload them, linking each to the user’s barcode.



Photo Access:





The user approaches a touch screen, inputs their barcode and phone number.



The system displays their photos.



Photo Selection and Customization:





The user selects photos, applies optional frames or filters, and chooses a package.



Checkout and Payment:





The user proceeds to the cashier, who scans the barcode to retrieve the order.



The cashier processes payment and prints selected photos.



A WhatsApp link with edited and all photos is sent, valid for 24 hours.



Synchronization:





Every 10 minutes, the branch’s local database syncs sales and order data to the central database.



Central Monitoring:





Admins access the central dashboard to view branch performance, sales, and sync logs.

7. Technical Specifications





Frontend: React.js for the touch-screen interface and staff dashboard.



Backend: Laravel 11 for API endpoints, database management, and synchronization.



Database:





Local: MySQL/PostgreSQL per branch.



Central: MySQL/PostgreSQL for aggregated data.



Storage: Photos stored in cloud storage (e.g., AWS S3) or local disk, with metadata in the local database.



APIs:





WhatsApp API (e.g., Twilio or Meta WhatsApp Cloud API) for sending photo links.



Barcode generation/scanning (e.g., JsBarcode for generation, USB scanner or HTML5 for scanning).



Hosting:





Local servers per branch (e.g., Dockerized Laravel instances).



Central server on cloud (e.g., AWS, Vercel).



Synchronization: Cron job or Laravel queue for syncing every 10 minutes.

8. Assumptions and Constraints





Assumptions:





Users access photos via barcode and phone number without accounts.



Staff have roles (e.g., photographer, cashier) managed in the users table.



Each branch operates independently with a local server and database.



Photos are high-resolution but optimized for storage and access.



Constraints:





The system is web-based, not a mobile app.



Synchronization occurs every 10 minutes, requiring reliable internet.



Photo links expire after 24 hours.



Touch screens are available at each branch.

9. Success Metrics





Users can access their photos within 5 seconds of entering valid barcode and phone number.



100% of uploaded photos are correctly linked to the user’s barcode.



Synchronization completes successfully every 10 minutes with no data loss.



Admin dashboard accurately reflects branch sales and order data.

10. Future Considerations





Add support for online payments (e.g., Stripe integration).



Implement photo editing features (e.g., cropping, advanced filters).



Extend the system to support multiple events or locations within a branch.



Add analytics for user behavior (e.g., most popular packages).