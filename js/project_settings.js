
    document.addEventListener("DOMContentLoaded", function() {
    const userInput = document.getElementById('user_input');
    const suggestionBox = document.getElementById('suggestion_box');

    async function searchUsers(keyword) {
        if (!keyword || keyword.length < 1) {
            suggestionBox.style.display = 'none';
            return;
        }

        try {
            const response = await fetch(`search_user.php?q=${encodeURIComponent(keyword)}`);
            const users = await response.json();
            
            renderSuggestions(users);
        } catch (error) {
            console.error('Lỗi tìm kiếm:', error);
        }
    }


    function renderSuggestions(users) {
        if (users.length === 0) {
            suggestionBox.style.display = 'none';
            return;
        }

        let html = '';
        users.forEach(user => {      
            html += `
                <div class="suggestion-item" onclick="selectUser('${user.user_name}')">
                    <span class="suggestion-name">${user.full_name} (${user.user_name})</span>
                    <span class="suggestion-email">${user.email}</span>
                </div>
            `;
        });

        suggestionBox.innerHTML = html;
        suggestionBox.style.display = 'block';
    }

    let timeout = null;
    userInput.addEventListener('input', function() {
        clearTimeout(timeout);
        const keyword = this.value.trim();
        
        timeout = setTimeout(() => {
            searchUsers(keyword);
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!userInput.contains(e.target) && !suggestionBox.contains(e.target)) {
            suggestionBox.style.display = 'none';
        }
    });
});

function selectUser(username) {
    const input = document.getElementById('user_input');
    const box = document.getElementById('suggestion_box');
    
    input.value = username; // Điền username vào ô input
    box.style.display = 'none'; // Ẩn hộp gợi ý
}
