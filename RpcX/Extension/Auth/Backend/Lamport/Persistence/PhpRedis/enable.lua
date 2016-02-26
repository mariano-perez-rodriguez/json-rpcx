-- ---------------------------------------------------------------------------------------------------------------------
-- Usage: enable  <token>  <prefix>
-- ---------------------------------------------------------------------------------------------------------------------

if #KEYS ~= 1 then return redis.error_reply("ERR faulty dummy in 'lamport.enable' script") end

-- extract token
local token = KEYS[1]

-- extract arguments
local prefix = ARGV[1]
--
local pendingKey = prefix .. ':pending:{' .. token .. '}'
local stateKey   = prefix .. ':state:{'   .. token .. '}'

-- check existing
if 0 ~= redis.call('EXISTS', stateKey) then
  return redis.error_reply("ERR token '" .. token .. "' already registered")
end

-- set pending
redis.call('SET', pendingKey, token)

return true
