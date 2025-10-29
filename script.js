const tabs = document.querySelectorAll('[data-tab-target]') //ability to select data
const tabContents = document.querySelectorAll('[data-tab-content]') //shows tab contents

tabs.forEach(tab => { //if click then tab will be active, if click another tab then it will deactivate previously activated tab and activate the new one.
  tab.addEventListener('click', () => {
    const target = document.querySelector(tab.dataset.tabTarget)
    tabContents.forEach(tabContent => {
      tabContent.classList.remove('active')
    })
    tabs.forEach(tab => {
      tab.classList.remove('active')
    })
    tab.classList.add('active')
    target.classList.add('active')
  })
})