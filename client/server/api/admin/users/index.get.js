import axios from 'axios'

export default defineEventHandler(async event => {
  console.log("123")

  const { q = '', role = null, plan = null, status = null, sortBy, itemsPerPage = 10, page = 1, orderBy } = getQuery(event)

  console.log("ppppppppppppppppppppppppp")

  // get users axios
  const { data: data } = await axios.get('http://localhost:8000/api/users', { params: { q, role, plan, status, sortBy, itemsPerPage, page, orderBy } })


  // filter users
  const dataFilter = data.data

  setResponseStatus(event, 200)
  
  return { users: dataFilter.data, totalPages: dataFilter.last_page, totalUsers: dataFilter.total, page: dataFilter.current_page }
})
