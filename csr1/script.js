window.addEventListener("load",function(){
setTimeout(function(){
document.getElementById("loader").style.display="none";
document.getElementById("main-content").style.display="block";
},2000)
})

const leaderboard=document.getElementById("leaderboard")

const csrNames=[
"Ana Lopez","Carlos Reyes","Angela Cruz","Mark Santos","James Tan",
"Liza Ramos","Paul Dizon","John Cruz","Maria Santos","Leo Garcia",
"Pat Torres","Anna Cruz","Kim Lee","Jason Tan","David Lim",
"Chris Bautista","Rose Alvarez","Jade Perez","Neil Gomez","Sarah Tan",
"Kevin Cruz","Kyle Ramos","Monica Yu","Dan Rivera","Eric Ong"
]

function randomOrders(){
return Math.floor(Math.random()*40)+5
}

function randomSales(){
return Math.floor(Math.random()*90000)+10000
}

function generateData(){
let data=[]
csrNames.forEach((name,i)=>{
data.push({
name:name,
orders:randomOrders(),
sales:randomSales(),
img:`https://i.pravatar.cc/100?img=${i+10}`
})
})
return data
}

let datasets=JSON.parse(localStorage.getItem("csrData"))

if(!datasets){
datasets={
daily:generateData(),
weekly:generateData(),
monthly:generateData()
}
localStorage.setItem("csrData",JSON.stringify(datasets))
}

function formatPeso(value){
return new Intl.NumberFormat('en-PH',{
style:'currency',
currency:'PHP'
}).format(value)
}

function loadLeaderboard(dataset){

leaderboard.innerHTML=""

dataset.sort((a,b)=>b.sales-a.sales)

dataset.slice(3).forEach((user,index)=>{

const row=document.createElement("div")
row.classList.add("row")

row.innerHTML=`
<span class="rank-circle">${index+4}</span>
<span class="name">
<img src="${user.img}">
${user.name}
</span>
<span>${user.orders}</span>
<span>${formatPeso(user.sales)}</span>
`

leaderboard.appendChild(row)

})
}

function updatePodium(dataset){

const sorted=[...dataset].sort((a,b)=>b.sales-a.sales)
const cards=document.querySelectorAll(".podium .card")

cards[1].querySelector(".avatar").src=sorted[0].img
cards[1].querySelector(".csr-name").textContent=sorted[0].name
cards[1].querySelector(".orders").textContent="Orders: "+sorted[0].orders
cards[1].querySelector(".sales").textContent=formatPeso(sorted[0].sales)

cards[0].querySelector(".avatar").src=sorted[1].img
cards[0].querySelector(".csr-name").textContent=sorted[1].name
cards[0].querySelector(".orders").textContent="Orders: "+sorted[1].orders
cards[0].querySelector(".sales").textContent=formatPeso(sorted[1].sales)

cards[2].querySelector(".avatar").src=sorted[2].img
cards[2].querySelector(".csr-name").textContent=sorted[2].name
cards[2].querySelector(".orders").textContent="Orders: "+sorted[2].orders
cards[2].querySelector(".sales").textContent=formatPeso(sorted[2].sales)

}

const tabs=document.querySelectorAll(".tab")

tabs.forEach(tab=>{
tab.addEventListener("click",()=>{
tabs.forEach(t=>t.classList.remove("active"))
tab.classList.add("active")

const type=tab.textContent.toLowerCase()

loadLeaderboard(datasets[type])
updatePodium(datasets[type])
})
})

loadLeaderboard(datasets.daily)
updatePodium(datasets.daily)