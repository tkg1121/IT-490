const questionContainer = document.getElementById('question-container');
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
