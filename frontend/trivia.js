document.addEventListener('DOMContentLoaded', function () {
    const questionElement = document.getElementById('trivia-question');
    const answersElement = document.getElementById('trivia-answers');
    const nextButton = document.getElementById('next-question');
    
    // Function to fetch a movie trivia question
    async function fetchTrivia() {
        const url = 'https://opentdb.com/api.php?amount=15&category=11&difficulty=medium&type=multiple';
        try {
            const response = await fetch(url);
            const data = await response.json();
            const trivia = data.results[0];
            displayTrivia(trivia);
        } catch (error) {
            questionElement.textContent = 'Failed to load trivia.';
            console.error('Error fetching trivia:', error);
        }
    }

    // Function to display trivia question and answers
    function displayTrivia(trivia) {
        // Decode HTML entities
        const question = decodeHTML(trivia.question);
        const correctAnswer = decodeHTML(trivia.correct_answer);
        const incorrectAnswers = trivia.incorrect_answers.map(answer => decodeHTML(answer));

        // Combine correct and incorrect answers, then shuffle them
        const answers = shuffleArray([correctAnswer, ...incorrectAnswers]);

        // Display the question
        questionElement.textContent = question;

        // Clear previous answers
        answersElement.innerHTML = '';

        // Display the answers as buttons
        answers.forEach(answer => {
            const answerButton = document.createElement('button');
            answerButton.textContent = answer;
            answerButton.onclick = function () {
                checkAnswer(answer, correctAnswer);
            };
            answersElement.appendChild(answerButton);
        });
    }

    // Function to check if the selected answer is correct
    function checkAnswer(selectedAnswer, correctAnswer) {
        if (selectedAnswer === correctAnswer) {
            alert('Correct!');
        } else {
            alert(`Wrong! The correct answer was ${correctAnswer}`);
        }
        fetchTrivia();  // Fetch next question after answer
    }

    // Function to decode HTML entities from API response
    function decodeHTML(html) {
        const text = document.createElement('textarea');
        text.innerHTML = html;
        return text.value;
    }

    // Function to shuffle array (Fisher-Yates algorithm)
    function shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
        return array;
    }

    // Fetch the first trivia question on page load
    fetchTrivia();

    // Load the next question when "Next Question" is clicked
    nextButton.addEventListener('click', fetchTrivia);
});


/* const questionContainer = document.getElementById('question-container');
const questionElement = document.getElementById('question');
const answerButtons = document.getElementById('answer-buttons');
const nextButton = document.getElementById('next-button');
const scoreContainer = document.getElementById('score-container');
const scoreElement = document.getElementById('score');

const questions = [
    {
        question: 'Who directed the movie "Inception"?',
        answers: [
            { text: 'Christopher Nolan', correct: true },
            { text: 'Steven Spielberg', correct: false },
            { text: 'James Cameron', correct: false },
            { text: 'Martin Scorsese', correct: false }
        ]
    },
    {
        question: 'What is the highest-grossing film of all time?',
        answers: [
            { text: 'Titanic', correct: false },
            { text: 'Avatar', correct: true },
            { text: 'The Avengers', correct: false },
            { text: 'Star Wars: The Force Awakens', correct: false }
        ]
    },
    {
        question: 'Which movie features the character "Jack Sparrow"?',
        answers: [
            { text: 'Pirates of the Caribbean', correct: true },
            { text: 'The Lord of the Rings', correct: false },
            { text: 'Harry Potter', correct: false },
            { text: 'The Matrix', correct: false }
        ]
    }
];

let currentQuestionIndex = 0;
let score = 0;

function startGame() {
    currentQuestionIndex = 0;
    score = 0;
    nextButton.classList.add('hide');
    scoreContainer.style.display = 'none';
    questionContainer.style.display = 'block';
    showQuestion(questions[currentQuestionIndex]);
}

function showQuestion(question) {
    questionElement.innerText = question.question;
    answerButtons.innerHTML = '';
    question.answers.forEach(answer => {
        const button = document.createElement('button');
        button.innerText = answer.text;
        button.classList.add('btn');
        button.addEventListener('click', () => selectAnswer(answer));
        answerButtons.appendChild(button);
    });
}

function selectAnswer(answer) {
    const correct = answer.correct;
    if (correct) {
        score++;
    }
    nextButton.classList.remove('hide');
}

function nextQuestion() {
    currentQuestionIndex++;
    if (currentQuestionIndex < questions.length) {
        showQuestion(questions[currentQuestionIndex]);
        nextButton.classList.add('hide');
    } else {
        questionContainer.style.display = 'none';
        scoreContainer.style.display = 'block';
        scoreElement.innerText = score;
    }
}

function restartGame() {
    startGame();
}

// Start the game on page load
startGame();

*/
