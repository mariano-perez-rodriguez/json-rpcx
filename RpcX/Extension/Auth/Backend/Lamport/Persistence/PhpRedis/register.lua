-- ---------------------------------------------------------------------------------------------------------------------
-- Usage: register  <token>  <prefix> <salt> <hash> <count>
-- ---------------------------------------------------------------------------------------------------------------------

if #KEYS ~= 1 then return redis.error_reply("ERR faulty dummy in 'lamport.register' script") end

-- extract token
local token = KEYS[1]

-- extract arguments
local prefix = ARGV[1]
local salt   = ARGV[2]
local hash   = ARGV[3]
local count  = ARGV[4]
--
local pendingKey = prefix .. ':pending:{' .. token .. '}'

-- check pending
if token ~= redis.call('GET', pendingKey) then
  redis.error_reply("ERR token not pending in 'lamport.register' script")
end

-- add data
local stateKey = prefix .. ':state:{' .. token .. '}'
--
redis.call('HMSET', stateKey, 'token', token, 'salt', salt, 'hash', hash, 'idx', count)
--
redis.call('DEL', pendingKey)

return true
