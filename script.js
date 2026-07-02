const apiUrl = 'https://api.allorigins.win/raw?url=https://cdn.deflock.me/alpr-counts.json'

async function init() {
    document.querySelectorAll('[data-dynamic-text]').forEach((el) => setTimeout(() => (el.style.opacity = '1'), 200 * el.dataset.fadeTime))

    try {
        const response = await fetch(apiUrl)
        const data = await response.json()
        document.getElementById('total-count').innerText = Number(data.us).toLocaleString('en-US')
    } catch (error) {
        console.error('Error fetching data:', error)
        document.getElementById('total-count').innerText = '>100,000'
    }
}

document.addEventListener('DOMContentLoaded', init)
