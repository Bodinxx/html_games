# System Design Specification: Activity Points Tracker
## 1. Project Overview
The Activity Points Tracker is a web-based application designed to monitor health metrics and calculate a weekly performance score. The system emphasizes simplicity, using a flat-file JSON database and a classic PHP/JS stack.

## 2. Technical Stack
- Frontend: HTML5, CSS3 (Custom Properties for Theming), Vanilla JavaScript.
- Backend: PHP 8.x (Logic and File I/O).
- Data Storage: Local JSON files (`users.json`, `activity_logs.json`, `activities.json`).
- Security: Simple PHP-based Captcha, Session management, and LocalStorage timers.

## 3. Data Architecture
### 3.1 User Schema (`users.json`)
    {
      "username": {
        "password_hash": "string",
        "last_login": "YYYY-MM-DD HH:MM:SS",
        "theme": "dark|light|ocean|dark blue|light blue|dark green|light green|dark red|light red|industrial",
		"role": "user|admin",
        "goals": {
          "avg_steps": 6000,
          "workout_hours": 5,
          "sleep_goal": 7,
          "clean_meals_goal": 14
        },
        "profile": {
          "full_name": "string",
          "weight": 80,
          "height": 180,
          "age": 30,
          "gender": "m|f"
        }
      }
    }

### 3.2 Activity Catalog (`activities.json`)
Read-only catalog defining conversion factors. Examples below. Will be adding more.
```
{
   "Cycling (Outdoor)": { "unit": "KM", "factor": 3, "category": "Cardio" },
   "Elliptical": { "unit": "Strides", "factor": 0.008, "category": "Cardio" },
   "Fast Paced Walking": { "unit": "Mins", "factor": 1.3, "category": "Cardio" },
   "Hiking (Hills)": { "unit": "Steps", "factor": 0.016, "category": "Cardio" },
   "Rucking (Weighted)": { "unit": "Steps", "factor": 0.015, "category": "Cardio" },
   "Running": { "unit": "Steps", "factor": 0.02, "category": "Cardio" },
   "Stair Climbing": { "unit": "Flights", "factor": 0.5, "category": "Cardio" },
   "Walking": { "unit": "Steps", "factor": 0.01, "category": "Cardio" }
 }
```

### 3.3 Activity Logs (`activity_logs.json`)
Data is indexed by Date of activity and username.
Structure: `logs[date][username] = { water, sleep, meals, activities: [] }`

## 4. Functional Modules

### 4.1 Onboarding & "Clean" Definition
- **Harris-Benedict Integration**: Calculates BMR to define meal "cleanliness" thresholds based on caloric needs.

### 4.2 Logging & "My Week" Interface
- **Integrated Dashboard**: A single-page interface combining data entry forms with real-time progress visualization.
- **Progress Tracking**: High-level summary of the current week's metrics vs. personal targets (Steps, Sleep, Meals).
- **Dynamic Points**: Updates "Activity Points" immediately upon logging via JS.

### 4.3 Scoring Logic
- **Weekly Activity Score**: Sum of `(Activity Quantity * Factor)` across all logged movements.
- **Final Output**: Total points and % of target achieved for the current ISO week.

## 5. System Features
### 5.1 Authentication & Admin
- **Sign-up**: Username, Password, and logic-based Captcha.
- **Persistence**: A JS timer stored in `localStorage` tracks the login timestamp; users are automatically logged out after 24 hours of inactivity.
- **Admin Panel**: Restricted view for:
  - Viewing all users and their last logged-in date.
  - Triggering password resets or removing users from `users.json`.
  - Managing the activity catalog.

### 5.2 User Theming
- **Implementation**: CSS Variables managed via user preference in users.json.

## 6. Security & Performance
- **Concurrency**: PHP flock() for JSON operations.
- **Privacy**: .htaccess blocks direct file access.
