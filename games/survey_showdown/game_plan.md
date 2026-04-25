"Survey Showdown" Project Plan

1. Difficulty Assessment
  - Overall Difficulty: Low to Moderate (3/10)
  - Logic (PHP/Backend): Low. The logic is primarily comparing a user string against an array of valid strings. The complexity comes in the "Fuzzy Logic," but PHP has built-in tools for this.
  - Interactivity (JavaScript): Moderate. You need to manage the game state (whose turn is it, how many strikes, revealing answers on the board).
  - Visuals (CSS): Moderate. Creating the classic "flip card" effect and a clean scoreboard requires some specific CSS3 transforms and Flexbox/Grid layouts.

2. Technical Architecture & File Structure
  - Directory Structure
    - Root Directory
      - index.php: The main entry point. It serves the HTML skeleton and includes the CSS/JS resources from the subdirectory.
    - Folder: survey_showdown/
      - questions.json: The raw data file containing questions, answers, points, and keywords.
      - api.php: The game engine logic. Handles AJAX requests from the frontend.
      - game.js: Contains all client-side logic (strikes, face-off, DOM updates).
      - style.css: Contains all visual styles for the board, scoreboard, and animations.
  
  - Backend Details (PHP)
    - index.php: Should link to resources using the path survey_showdown/style.css and survey_showdown/game.js.
    - survey_showdown/api.php:
      - Data Access: Reads the questions from questions.json (which is in the same directory, accessible via __DIR__ . '/questions.json').
      - Action get_question: Returns a random question ID and the question text (but NOT the answers) to the JavaScript.
      - Action check_answer: Receives {question_id, user_answer} from JS. It loads the JSON, finds the question, and runs the fuzzy matching logic. It returns {correct: boolean, points: int, reveal_text: string, rank: int}.

  - Frontend Details (JS & CSS)
    - survey_showdown/game.js:
      - Fetches data from the API endpoint: survey_showdown/api.php.
      - Manages "Strikes" (X markers).
      - Manages the "Face-off" (who buzzed in).
      - Updates the DOM board when an answer is correct.
    - survey_showdown/style.css:
      - Needs a big board layout (typically 2 columns of 4 rows).
      - Needs a strike overlay (the big red X).
      - Needs a scoreboard for Player 1 and Player 2.

3. The "Fuzzy Logic" Strategy
  Strict string matching (if input == answer) is frustrating for this type of game. "Lawn" should match "Grass".
  
  Level 1: Keyword Inclusion (The Easy Way) In the JSON provided, I included a keywords array.
    Logic: Check if the user's input appears in the keywords array OR if the keywords strings appear in the user's input.
    Example: User types "I hate eating broccoli". The keyword "broccoli" matches.

  Level 2: Levenshtein Distance (The "Pro" Way) PHP has a function levenshtein($str1, $str2). It calculates how many characters you have to change to make the strings match.
    Logic: If levenshtein(user_input, answer_keyword) < 3, count it as correct. This handles typos like "banaana" (vs "banana").

  Level 3: Phonetic Matching  PHP has metaphone() or soundex().
    Logic: if (metaphone($user_input) == metaphone($answer)). This handles "nife" vs "knife".

4. Two-Player Flow (1v1)
  Since it's 2-player exclusive at the start:
    1. The Face-Off: Question appears. Both players race to hit their buzzer key (e.g., 'A' for Player 1, 'L' for Player 2).
    2. Winner of Face-Off: Gets 10 seconds to type an answer.
        - If correct: They gain control of the board.
        - If incorrect: Opponent gets a chance to steal control.
    3. The Board Run: The controlling player keeps guessing to uncover answers.
        - 3 Strikes (incorrect guesses) passes control to the opponent.
    4. The Steal: If 3 strikes occur, the opponent gets one guess.
        - If they get it right: They steal all the points in the bank.
        - If they get it wrong: The original player keeps the points.

5. Next Steps
  To build this, we would write the survey_showdown/api.php to parse the questions.json and the frontend to display it.