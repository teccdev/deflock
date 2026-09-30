const apiUrl = 'counts.php'

async function init() {
    document.querySelectorAll('[data-dynamic-text]').forEach((el) => setTimeout(() => (el.style.opacity = '1'), 200 * el.dataset.fadeTime))

    try {
        const response = await fetch(apiUrl)
        if (!response.ok) throw new Error(`Request failed with status ${response.status}`)

        const data = await response.json()
        const us = Number(data.us)
        if (!Number.isFinite(us)) throw new Error('Response did not contain a valid US count')

        document.getElementById('total-count').innerText = us.toLocaleString('en-US')
    } catch (error) {
        console.error('Error fetching data:', error)
        document.getElementById('total-count').innerText = '>100,000'
    }
}

document.addEventListener('DOMContentLoaded', init)
